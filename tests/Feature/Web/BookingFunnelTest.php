<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\Booking;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed();
    // Moyasar invoice creation — the funnel books through the same gateway.
    Http::fake([
        '*moyasar*' => Http::response([
            'id' => 'inv_funnel_1',
            'status' => 'initiated',
            'url' => 'https://moyasar.test/pay/inv_funnel_1',
        ], 201),
    ]);
});

function funnelPlace(array $attrs = []): Place
{
    $host = User::factory()->create(['phone' => '518400'.fake()->unique()->numerify('###')]);

    return Place::query()->create(array_merge([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Funnel chalet',
        'description' => 'x',
        'price' => 1000,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ], $attrs));
}

it('renders the checkout page for valid dates and bounces invalid ones back', function (): void {
    $place = funnelPlace();
    $in = now()->addDays(5)->toDateString();
    $out = now()->addDays(7)->toDateString();

    // Valid stay → summary page with the place + CTA wiring.
    $this->get(route('book.checkout', ['place' => $place, 'check_in' => $in, 'check_out' => $out]))
        ->assertOk()
        ->assertSee('تأكيد و دفع')
        ->assertSee($place->title_ar)
        ->assertSee('الإجمالى');

    // Missing, malformed, inverted, or past dates → back to the listing.
    $this->get(route('book.checkout', $place))
        ->assertRedirect(route('places.show', $place));
    $this->get(route('book.checkout', ['place' => $place, 'check_in' => 'nope', 'check_out' => $out]))
        ->assertRedirect(route('places.show', $place));
    $this->get(route('book.checkout', ['place' => $place, 'check_in' => $out, 'check_out' => $in]))
        ->assertRedirect(route('places.show', $place));
    $this->get(route('book.checkout', ['place' => $place, 'check_in' => now()->subDays(3)->toDateString(), 'check_out' => $in]))
        ->assertRedirect(route('places.show', $place));

    // Draft places 404 like the funnel itself.
    $draft = funnelPlace(['review_status' => PlaceReviewStatus::Draft->value, 'status' => PlaceStatus::Inactive->value]);
    $this->get(route('book.checkout', ['place' => $draft, 'check_in' => $in, 'check_out' => $out]))
        ->assertNotFound();
});

it('renders the funnel page for a live place and 404s for a draft', function (): void {
    $live = funnelPlace();
    $draft = funnelPlace(['review_status' => PlaceReviewStatus::Draft->value, 'status' => PlaceStatus::Inactive->value]);

    $this->get(route('book.show', $live))
        ->assertOk()
        ->assertSee('Funnel chalet', escape: false);

    $this->get(route('book.show', $draft))->assertNotFound();
});

it('creates a pending booking and returns the payment url, with the web return_url on the invoice', function (): void {
    $place = funnelPlace();
    $guest = User::factory()->create(['phone' => '518400901']);

    $response = $this->actingAs($guest, 'api')
        ->postJson(route('book.store', $place), [
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guests' => 2,
        ])
        ->assertOk();

    $booking = Booking::query()->where('place_id', $place->id)->sole();
    expect($booking->booking_status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->guest_user_id)->toBe($guest->id)
        ->and($response->json('payment_url'))->toBe('https://moyasar.test/pay/inv_funnel_1')
        ->and($response->json('status_url'))->toBe(route('book.status', $booking));

    // Moyasar got OUR web status page on success; back gets its own
    // settling URL (?back=1).
    Http::assertSent(function ($request) use ($booking): bool {
        if (! str_contains($request->url(), 'invoices')) {
            return false;
        }
        $data = $request->data();

        return ($data['success_url'] ?? null) === route('book.status', $booking)
            && ($data['back_url'] ?? null) === route('book.status', ['booking' => $booking, 'back' => 1]);
    });
});

it('fills a blank profile name during the funnel booking', function (): void {
    $place = funnelPlace();
    $guest = User::factory()->create(['phone' => '518400902', 'name' => null]);

    $this->actingAs($guest, 'api')
        ->postJson(route('book.store', $place), [
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(6)->toDateString(),
            'guests' => 1,
            'name' => 'عبدالله القحطاني',
        ])
        ->assertOk();

    expect($guest->refresh()->name)->toBe('عبدالله القحطاني');
});

it('does not overwrite an existing profile name', function (): void {
    $place = funnelPlace();
    $guest = User::factory()->create(['phone' => '518400903', 'name' => 'الاسم الأصلي']);

    $this->actingAs($guest, 'api')
        ->postJson(route('book.store', $place), [
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(6)->toDateString(),
            'guests' => 1,
            'name' => 'اسم مختلف',
        ])
        ->assertOk();

    expect($guest->refresh()->name)->toBe('الاسم الأصلي');
});

it('requires auth to book and guards the status page to the booking owner', function (): void {
    $place = funnelPlace();

    $this->postJson(route('book.store', $place), [
        'check_in' => now()->addDays(5)->toDateString(),
        'check_out' => now()->addDays(6)->toDateString(),
        'guests' => 1,
    ])->assertUnauthorized();

    $guest = User::factory()->create(['phone' => '518400904']);
    $stranger = User::factory()->create(['phone' => '518400905']);

    $this->actingAs($guest, 'api')->postJson(route('book.store', $place), [
        'check_in' => now()->addDays(5)->toDateString(),
        'check_out' => now()->addDays(6)->toDateString(),
        'guests' => 1,
    ])->assertOk();

    $booking = Booking::query()->where('place_id', $place->id)->sole();

    $this->actingAs($guest, 'api')->get(route('book.status', $booking))->assertOk();
    $this->actingAs($stranger, 'api')->get(route('book.status', $booking))->assertNotFound();
});

it('keeps the app return urls when no return_url option is passed', function (): void {
    $place = funnelPlace();
    $guest = User::factory()->create(['phone' => '518400906']);

    // App flow: the API bookings endpoint, not the funnel.
    $this->actingAs($guest, 'api')
        ->postJson("/api/places/{$place->id}/bookings", [
            'check_in' => now()->addDays(9)->toDateString(),
            'check_out' => now()->addDays(10)->toDateString(),
            'guests' => 1,
        ])
        ->assertCreated();

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), 'invoices')) {
            return false;
        }
        $data = $request->data();

        return ($data['success_url'] ?? null) === config('moyasar.success_url')
            && ($data['back_url'] ?? null) === config('moyasar.back_url');
    });
});

it('gives Moyasar a distinct back url that releases the hold on return', function (): void {
    $place = funnelPlace();
    $guest = User::factory()->create(['phone' => '518400907']);

    $this->actingAs($guest, 'api')
        ->postJson(route('book.store', $place), [
            'check_in' => now()->addDays(12)->toDateString(),
            'check_out' => now()->addDays(13)->toDateString(),
            'guests' => 1,
        ])->assertOk();

    $booking = Booking::query()->where('place_id', $place->id)->sole();

    // The invoice got success → status page, back → status page + ?back=1.
    Http::assertSent(function ($request) use ($booking): bool {
        if (! str_contains($request->url(), 'invoices')) {
            return false;
        }
        $data = $request->data();

        return ($data['success_url'] ?? null) === route('book.status', $booking)
            && ($data['back_url'] ?? null) === route('book.status', ['booking' => $booking, 'back' => 1]);
    });

    // Backing out lands on the status page, which settles the booking:
    // still unpaid (gateway says initiated) → the hold is released as
    // Expired (same as the app: an abandoned hold, not a cancellation).
    $this->actingAs($guest, 'api')
        ->get(route('book.status', ['booking' => $booking, 'back' => 1]))
        ->assertOk();

    expect($booking->refresh()->booking_status)->toBe(BookingStatus::Expired);

    // Refreshing the back URL is harmless — already settled, no-op.
    $this->actingAs($guest, 'api')
        ->get(route('book.status', ['booking' => $booking, 'back' => 1]))
        ->assertOk();
    expect($booking->refresh()->booking_status)->toBe(BookingStatus::Expired);
});
