<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;

beforeEach(function (): void {
    $this->seed();
    $this->admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '598200001']);
    $this->host = User::factory()->create(['phone' => '516200001', 'bank' => 'Al Rajhi', 'bank_account' => 'SA4420000001234567891234']);
    $this->guest = User::factory()->create(['phone' => '517200001']);
});

function payoutPlace(User $host): Place
{
    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Payout place '.fake()->unique()->numerify('###'),
        'price' => 1000, 'check_in_time' => '15:00', 'check_out_time' => '12:00',
        'max_guests' => 4, 'status' => PlaceStatus::Active->value, 'review_status' => PlaceReviewStatus::Approved->value,
    ]);
}

function payoutBooking(Place $place, User $guest, array $attrs = []): Booking
{
    return Booking::query()->create(array_merge([
        'place_id' => $place->id,
        'guest_user_id' => $guest->id,
        'host_user_id' => $place->host_user_id,
        'booking_status' => BookingStatus::Completed->value,
        'start_date' => now()->subDays(4)->toDateString(),
        'end_date' => now()->subDays(3)->toDateString(),
        'guests' => 2, 'booking_price' => 100000, 'quantity' => 2, 'booking_amount' => 200000,
        'commission_rate' => 10, 'commission_amount' => 20000, 'vat_rate' => 15, 'vat_amount' => 30000,
        'total' => 230000, 'payout_status' => 'not_paid', 'confirmed_at' => now()->subDays(6),
        // Payable state: mark-paid requires issued documents + a passed hold
        // window (checkout was 3 days ago, so the 24h hold has cleared).
        'financial_completed_at' => now()->subDays(2),
    ], $attrs));
}

it('queues only completed, not-yet-paid bookings with host net + IBAN', function (): void {
    $place = payoutPlace($this->host);
    $payable = payoutBooking($place, $this->guest);
    $upcoming = payoutBooking($place, $this->guest, ['booking_status' => BookingStatus::Confirmed->value]);
    $alreadyPaid = payoutBooking($place, $this->guest, ['payout_status' => 'paid', 'paid_out_at' => now()]);
    $cancelled = payoutBooking($place, $this->guest, ['booking_status' => BookingStatus::CanceledByGuest->value]);

    $this->actingAs($this->admin, 'api')
        ->get('/admin/payouts')
        ->assertOk()
        ->assertSee($payable->reference)
        ->assertDontSee($upcoming->reference)
        ->assertDontSee($alreadyPaid->reference)
        ->assertDontSee($cancelled->reference)
        // Host net = 200000 − 20000 = SR 1,800.00, and the IBAN for the transfer.
        ->assertSee('1,770.00')
        ->assertSee('SA4420000001234567891234');
});

it('itemizes the invoice per night, and falls back to an average when prices drifted', function (): void {
    $place = payoutPlace($this->host); // SR 1,000/night, no weekday overrides

    // Snapshot still matches the place's current prices → true per-night lines.
    payoutBooking($place, $this->guest); // 2 nights × 1,000.00 = booking_amount 200000

    // Snapshot no longer reconstructable (host changed prices since) → the
    // invoice must show an honest nights × average line, never a made-up split.
    payoutBooking($place, $this->guest, [
        'booking_amount' => 150000, 'commission_amount' => 15000,
        'vat_amount' => 22500, 'total' => 172500,
    ]);

    $this->actingAs($this->admin, 'api')
        ->get('/admin/payouts')
        ->assertOk()
        ->assertSee('SR 1,000.00')  // exact nightly rate line
        ->assertSee('750.00');      // drifted: 150000 / 2 nights avg
});

it('marks a completed booking paid with an audit trail, and undo re-queues it', function (): void {
    $booking = payoutBooking(payoutPlace($this->host), $this->guest);

    $this->actingAs($this->admin, 'api')
        ->post("/admin/bookings/{$booking->id}/payout", [
            'payout_status' => 'paid',
            'payout_reference' => 'TRF-20260702-01',
        ])
        ->assertRedirect();

    $booking->refresh();
    expect($booking->payout_status)->toBe('paid')
        ->and($booking->paid_out_at)->not->toBeNull()
        ->and($booking->payout_reference)->toBe('TRF-20260702-01');

    // Consume the flash banner (it echoes the reference) so the queue
    // assertions below only match actual rows.
    $this->actingAs($this->admin, 'api')->get('/admin/bookings');

    // It moved to the paid tab.
    $this->actingAs($this->admin, 'api')->get('/admin/payouts')->assertDontSee($booking->reference);
    $this->actingAs($this->admin, 'api')->get('/admin/payouts?tab=paid')->assertSee($booking->reference);

    // Undo: back to the queue, audit fields cleared.
    $this->actingAs($this->admin, 'api')
        ->post("/admin/bookings/{$booking->id}/payout", ['payout_status' => 'not_paid'])
        ->assertRedirect();

    $booking->refresh();
    expect($booking->payout_status)->toBe('not_paid')
        ->and($booking->paid_out_at)->toBeNull()
        ->and($booking->payout_reference)->toBeNull();
});

it('refuses to pay out a booking that has not completed', function (): void {
    $confirmed = payoutBooking(payoutPlace($this->host), $this->guest, [
        'booking_status' => BookingStatus::Confirmed->value,
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
    ]);

    $this->actingAs($this->admin, 'api')
        ->from('/admin/payouts')
        ->post("/admin/bookings/{$confirmed->id}/payout", ['payout_status' => 'paid'])
        ->assertSessionHasErrors(['payout']);

    expect($confirmed->fresh()->payout_status)->toBe('not_paid');
});

it('feeds the host earnings buckets: paid moves from pending to paid', function (): void {
    $booking = payoutBooking(payoutPlace($this->host), $this->guest);

    // Host's mobile earnings before: everything pending.
    $this->actingAs($this->host, 'api')
        ->getJson('/api/host/earnings')
        ->assertJsonPath('data.not_paid_minor', 177000)
        ->assertJsonPath('data.paid_minor', 0);

    $this->actingAs($this->admin, 'api')
        ->post("/admin/bookings/{$booking->id}/payout", ['payout_status' => 'paid']);

    $this->actingAs($this->host, 'api')
        ->getJson('/api/host/earnings')
        ->assertJsonPath('data.not_paid_minor', 0)
        ->assertJsonPath('data.paid_minor', 177000);
});

it('searches the queue by host phone', function (): void {
    $mine = payoutBooking(payoutPlace($this->host), $this->guest);

    $otherHost = User::factory()->create(['phone' => '516200099']);
    $theirs = payoutBooking(payoutPlace($otherHost), $this->guest);

    $this->actingAs($this->admin, 'api')
        ->get('/admin/payouts?q=516200001')
        ->assertOk()
        ->assertSee($mine->reference)
        ->assertDontSee($theirs->reference);
});

it('is admin-only', function (): void {
    $booking = payoutBooking(payoutPlace($this->host), $this->guest);

    // EnsureAdmin bounces non-admin web users to their profile.
    $this->actingAs($this->host, 'api')->get('/admin/payouts')->assertRedirect('/profile');
    $this->actingAs($this->host, 'api')
        ->post("/admin/bookings/{$booking->id}/payout", ['payout_status' => 'paid'])
        ->assertRedirect('/profile');

    expect($booking->fresh()->payout_status)->toBe('not_paid');
});
