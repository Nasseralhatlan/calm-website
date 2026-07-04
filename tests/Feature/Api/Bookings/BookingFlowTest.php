<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Jobs\ExpireStaleBookings;
use App\Models\Booking;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;
use App\Services\Booking\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed();
});

/** Active+Approved place: base 1000/day, max 4 guests. */
function bookingPlace(array $attrs = []): Place
{
    $host = User::factory()->create(['phone' => '515'.fake()->unique()->numerify('######')]);

    return Place::query()->create(array_merge([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Booking test place',
        'description' => 'Desc.',
        'price' => 1000,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'rules' => 'No smoking.',
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ], $attrs));
}

function bookingGuest(): User
{
    return User::factory()->create(['phone' => '516'.fake()->unique()->numerify('######')]);
}

/** Fake the Moyasar invoices API: creation returns `initiated`, fetch returns the given status/amount. */
function fakeMoyasar(string $fetchStatus = 'initiated', int $fetchAmount = 0): void
{
    Http::fake([
        // `*/cancel` must precede the `*` fetch pattern (it's more specific).
        'api.moyasar.com/v1/invoices/*/cancel' => Http::response([
            'id' => 'inv_test', 'status' => 'canceled', 'amount' => 0,
            'url' => 'https://checkout.moyasar.com/invoices/inv_test', 'metadata' => [],
        ]),
        'api.moyasar.com/v1/invoices/*' => Http::response([
            'id' => 'inv_test', 'status' => $fetchStatus, 'amount' => $fetchAmount,
            'url' => 'https://checkout.moyasar.com/invoices/inv_test', 'metadata' => [],
            'payments' => $fetchStatus === 'paid'
                ? [['status' => 'paid', 'amount' => $fetchAmount, 'source' => ['type' => 'creditcard']]]
                : [],
        ]),
        'api.moyasar.com/v1/invoices' => Http::response([
            'id' => 'inv_test', 'status' => 'initiated', 'amount' => 0,
            'url' => 'https://checkout.moyasar.com/invoices/inv_test', 'metadata' => [],
        ]),
    ]);
}

// 2 inclusive days @ 1000 → subtotal 2000, VAT 15% = 300, total 2300 (230000 halalas).
function twoNightDates(): array
{
    return [now()->addDays(3)->toDateString(), now()->addDays(4)->toDateString()];
}

it('creates a pending booking and returns a Moyasar payment url', function (): void {
    fakeMoyasar();
    $guest = bookingGuest();
    $place = bookingPlace();
    [$in, $out] = twoNightDates();

    $this->actingAs($guest, 'api')
        ->postJson("/api/places/{$place->id}/bookings", ['check_in' => $in, 'check_out' => $out, 'guests' => 2])
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'pending_payment')
        ->assertJsonPath('data.guests', 2)
        ->assertJsonPath('data.pricing.subtotal', 2000)
        ->assertJsonPath('data.pricing.vat', 300)
        ->assertJsonPath('data.pricing.total', 2300)
        ->assertJsonPath('data.pricing.total_minor', 230000)
        ->assertJsonPath('data.payment.url', 'https://checkout.moyasar.com/invoices/inv_test');

    $booking = Booking::query()->first();
    expect($booking->booking_status)->toBe(BookingStatus::PendingPayment);
    expect($booking->guest_user_id)->toBe($guest->id);
    expect($booking->host_user_id)->toBe($place->host_user_id);
    expect($booking->guest_total)->toBe(230000);
    expect($booking->commission_amount_ex_vat)->toBe(20000); // 2000 × 10% seeded, host-side
    expect($booking->payment_id)->toBe('inv_test');
});

it('holds the dates so the place is no longer bookable for an overlapping stay', function (): void {
    fakeMoyasar();
    $place = bookingPlace();
    [$in, $out] = twoNightDates();

    $this->actingAs(bookingGuest(), 'api')
        ->postJson("/api/places/{$place->id}/bookings", ['check_in' => $in, 'check_out' => $out, 'guests' => 2])
        ->assertStatus(201);

    // The quote now reports those dates as unavailable…
    $this->getJson("/api/places/{$place->id}/quote?check_in={$in}&check_out={$out}")
        ->assertOk()
        ->assertJsonPath('data.dates_available', false);

    // …and a second guest can't book them.
    $this->actingAs(bookingGuest(), 'api')
        ->postJson("/api/places/{$place->id}/bookings", ['check_in' => $in, 'check_out' => $out, 'guests' => 2])
        ->assertStatus(422);

    expect(Booking::query()->count())->toBe(1);
});

it('confirms the booking when Moyasar reports it paid', function (): void {
    fakeMoyasar('paid', 230000);
    $guest = bookingGuest();
    $place = bookingPlace();
    [$in, $out] = twoNightDates();

    $this->actingAs($guest, 'api')
        ->postJson("/api/places/{$place->id}/bookings", ['check_in' => $in, 'check_out' => $out, 'guests' => 2])
        ->assertStatus(201);

    $booking = Booking::query()->first();

    $this->actingAs($guest, 'api')
        ->getJson("/api/bookings/{$booking->id}/payment-status")
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    $booking->refresh();
    expect($booking->booking_status)->toBe(BookingStatus::Confirmed);
    expect($booking->confirmed_at)->not->toBeNull();
    expect($booking->payment_method)->toBe('creditcard');
    expect($booking->expires_at)->toBeNull();
});

it('never confirms when the paid amount does not match the quote', function (): void {
    fakeMoyasar('paid', 999999); // tampered/wrong amount
    $guest = bookingGuest();
    $place = bookingPlace();
    [$in, $out] = twoNightDates();

    $this->actingAs($guest, 'api')
        ->postJson("/api/places/{$place->id}/bookings", ['check_in' => $in, 'check_out' => $out, 'guests' => 2])
        ->assertStatus(201);

    $booking = Booking::query()->first();

    $this->actingAs($guest, 'api')
        ->getJson("/api/bookings/{$booking->id}/payment-status")
        ->assertOk();

    expect($booking->refresh()->booking_status)->toBe(BookingStatus::Expired);
});

it('forbids polling another guest\'s booking', function (): void {
    fakeMoyasar();
    $owner = bookingGuest();
    $place = bookingPlace();
    [$in, $out] = twoNightDates();

    $this->actingAs($owner, 'api')
        ->postJson("/api/places/{$place->id}/bookings", ['check_in' => $in, 'check_out' => $out, 'guests' => 2])
        ->assertStatus(201);

    $booking = Booking::query()->first();

    $this->actingAs(bookingGuest(), 'api')
        ->getJson("/api/bookings/{$booking->id}/payment-status")
        ->assertForbidden();
});

it('confirms via the Moyasar webhook', function (): void {
    fakeMoyasar('paid', 230000);
    $place = bookingPlace();
    [$in, $out] = twoNightDates();

    $this->actingAs(bookingGuest(), 'api')
        ->postJson("/api/places/{$place->id}/bookings", ['check_in' => $in, 'check_out' => $out, 'guests' => 2])
        ->assertStatus(201);

    $booking = Booking::query()->first();

    // Moyasar's server-to-server callback (no auth).
    $this->postJson('/api/payments/moyasar/webhook', [
        'type' => 'invoice_paid',
        'data' => ['id' => $booking->payment_id, 'status' => 'paid', 'metadata' => ['booking_id' => $booking->id]],
    ])->assertOk();

    expect($booking->refresh()->booking_status)->toBe(BookingStatus::Confirmed);
});

it('rejects a webhook with the wrong secret when one is configured', function (): void {
    config(['moyasar.webhook_secret' => 'top-secret']);

    $this->postJson('/api/payments/moyasar/webhook', ['data' => ['id' => 'inv_test']])
        ->assertStatus(401);
});

it('expires a stale pending hold and frees the dates', function (): void {
    fakeMoyasar('initiated'); // still unpaid when the sweep checks
    $place = bookingPlace();
    [$in, $out] = twoNightDates();

    $this->actingAs(bookingGuest(), 'api')
        ->postJson("/api/places/{$place->id}/bookings", ['check_in' => $in, 'check_out' => $out, 'guests' => 2])
        ->assertStatus(201);

    $booking = Booking::query()->first();
    $booking->update(['expires_at' => now()->subMinute()]); // hold lapsed

    (new ExpireStaleBookings)->handle(app(BookingService::class));

    expect($booking->refresh()->booking_status)->toBe(BookingStatus::Expired);

    // Dates are available again.
    $this->getJson("/api/places/{$place->id}/quote?check_in={$in}&check_out={$out}")
        ->assertOk()
        ->assertJsonPath('data.dates_available', true);
});

it('rescues a paid-but-unconfirmed hold during the sweep', function (): void {
    fakeMoyasar('paid', 230000);
    $place = bookingPlace();
    [$in, $out] = twoNightDates();

    $this->actingAs(bookingGuest(), 'api')
        ->postJson("/api/places/{$place->id}/bookings", ['check_in' => $in, 'check_out' => $out, 'guests' => 2])
        ->assertStatus(201);

    $booking = Booking::query()->first();
    $booking->update(['expires_at' => now()->subMinute()]);

    (new ExpireStaleBookings)->handle(app(BookingService::class));

    expect($booking->refresh()->booking_status)->toBe(BookingStatus::Confirmed);
});

it('rejects a party larger than the place allows', function (): void {
    fakeMoyasar();
    $place = bookingPlace(['max_guests' => 4]);
    [$in, $out] = twoNightDates();

    $this->actingAs(bookingGuest(), 'api')
        ->postJson("/api/places/{$place->id}/bookings", ['check_in' => $in, 'check_out' => $out, 'guests' => 10])
        ->assertStatus(422);

    expect(Booking::query()->count())->toBe(0);
});

it('rejects a check-in date in the past', function (): void {
    fakeMoyasar();
    $place = bookingPlace();

    $this->actingAs(bookingGuest(), 'api')
        ->postJson("/api/places/{$place->id}/bookings", [
            'check_in' => now()->subDay()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'guests' => 2,
        ])
        ->assertStatus(422);
});

it('requires authentication to book', function (): void {
    $place = bookingPlace();
    [$in, $out] = twoNightDates();

    $this->postJson("/api/places/{$place->id}/bookings", ['check_in' => $in, 'check_out' => $out, 'guests' => 2])
        ->assertStatus(401);
});

it('cancels a pending booking on back and frees the dates', function (): void {
    fakeMoyasar('initiated'); // unpaid when cancel re-checks
    $guest = bookingGuest();
    $place = bookingPlace();
    [$in, $out] = twoNightDates();

    $this->actingAs($guest, 'api')
        ->postJson("/api/places/{$place->id}/bookings", ['check_in' => $in, 'check_out' => $out, 'guests' => 2])
        ->assertStatus(201);

    $booking = Booking::query()->first();

    $this->actingAs($guest, 'api')
        ->postJson("/api/bookings/{$booking->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'expired');

    expect($booking->refresh()->booking_status)->toBe(BookingStatus::Expired);

    // Dates are available again.
    $this->getJson("/api/places/{$place->id}/quote?check_in={$in}&check_out={$out}")
        ->assertOk()
        ->assertJsonPath('data.dates_available', true);
});

it('confirms instead of cancelling when the payment actually completed', function (): void {
    fakeMoyasar('paid', 230000); // Moyasar reports paid when cancel re-checks
    $guest = bookingGuest();
    $place = bookingPlace();
    [$in, $out] = twoNightDates();

    $this->actingAs($guest, 'api')
        ->postJson("/api/places/{$place->id}/bookings", ['check_in' => $in, 'check_out' => $out, 'guests' => 2])
        ->assertStatus(201);

    $booking = Booking::query()->first();

    $this->actingAs($guest, 'api')
        ->postJson("/api/bookings/{$booking->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    expect($booking->refresh()->booking_status)->toBe(BookingStatus::Confirmed);
});

it('does not cancel an already-confirmed booking', function (): void {
    fakeMoyasar('paid', 230000);
    $guest = bookingGuest();
    $place = bookingPlace();
    [$in, $out] = twoNightDates();

    $this->actingAs($guest, 'api')
        ->postJson("/api/places/{$place->id}/bookings", ['check_in' => $in, 'check_out' => $out, 'guests' => 2])
        ->assertStatus(201);

    $booking = Booking::query()->first();
    // Confirm it first.
    $this->actingAs($guest, 'api')->getJson("/api/bookings/{$booking->id}/payment-status")->assertOk();
    expect($booking->refresh()->booking_status)->toBe(BookingStatus::Confirmed);

    // A later cancel is a no-op.
    $this->actingAs($guest, 'api')
        ->postJson("/api/bookings/{$booking->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    expect($booking->refresh()->booking_status)->toBe(BookingStatus::Confirmed);
});

it('forbids cancelling another guest\'s booking', function (): void {
    fakeMoyasar();
    $owner = bookingGuest();
    $place = bookingPlace();
    [$in, $out] = twoNightDates();

    $this->actingAs($owner, 'api')
        ->postJson("/api/places/{$place->id}/bookings", ['check_in' => $in, 'check_out' => $out, 'guests' => 2])
        ->assertStatus(201);

    $booking = Booking::query()->first();

    $this->actingAs(bookingGuest(), 'api')
        ->postJson("/api/bookings/{$booking->id}/cancel")
        ->assertForbidden();

    expect($booking->refresh()->booking_status)->toBe(BookingStatus::PendingPayment);
});

it('serves the payment-return landing pages', function (): void {
    $this->get('/calm-after-payment')
        ->assertOk()
        ->assertSee('data-payment-return="success"', false);

    $this->get('/calm-back-payment')
        ->assertOk()
        ->assertSee('data-payment-return="cancelled"', false);
});

it('expires the Moyasar invoice a buffer before the date hold', function (): void {
    config(['moyasar.hold_minutes' => 10, 'moyasar.invoice_buffer_minutes' => 1]);
    fakeMoyasar();
    $guest = bookingGuest();
    $place = bookingPlace();
    [$in, $out] = twoNightDates();

    $this->actingAs($guest, 'api')
        ->postJson("/api/places/{$place->id}/bookings", ['check_in' => $in, 'check_out' => $out, 'guests' => 2])
        ->assertStatus(201);

    $booking = Booking::query()->first();

    // Capture the invoice-creation request and read the expiry we sent Moyasar.
    $invoiceExpiry = null;
    Http::assertSent(function ($request) use (&$invoiceExpiry) {
        if ($request->url() === 'https://api.moyasar.com/v1/invoices' && $request->method() === 'POST') {
            $invoiceExpiry = CarbonImmutable::parse($request['expired_at']);

            return true;
        }

        return false;
    });

    expect($invoiceExpiry)->not->toBeNull()
        // Invoice closes before the date hold...
        ->and($invoiceExpiry->lessThan($booking->expires_at))->toBeTrue()
        // ...by the configured buffer (≈ 1 minute).
        ->and(abs($booking->expires_at->diffInSeconds($invoiceExpiry)))->toBeGreaterThanOrEqual(55)
        ->and(abs($booking->expires_at->diffInSeconds($invoiceExpiry)))->toBeLessThanOrEqual(65);
});

it('snapshots the place checkout_next_day onto the booking', function (): void {
    fakeMoyasar();
    $guest = bookingGuest();
    $place = bookingPlace(['checkout_next_day' => false]);
    [$in, $out] = twoNightDates();

    $this->actingAs($guest, 'api')
        ->postJson("/api/places/{$place->id}/bookings", ['check_in' => $in, 'check_out' => $out, 'guests' => 2])
        ->assertStatus(201)
        ->assertJsonPath('data.checkout_next_day', false);

    expect(Booking::query()->first()->checkout_next_day)->toBeFalse();
});
