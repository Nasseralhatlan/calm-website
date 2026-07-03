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

beforeEach(function (): void {
    $this->seed();
    $this->host = User::factory()->create(['phone' => '516300001']);
    $this->guest = User::factory()->create(['phone' => '517300001']);
});

function finPlace(User $host): Place
{
    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Finance place', 'price' => 1000, 'check_in_time' => '15:00', 'check_out_time' => '12:00',
        'max_guests' => 4, 'status' => PlaceStatus::Active->value, 'review_status' => PlaceReviewStatus::Approved->value,
    ]);
}

function finBooking(Place $place, User $guest, array $attrs = []): Booking
{
    return Booking::query()->create(array_merge([
        'place_id' => $place->id,
        'guest_user_id' => $guest->id,
        'host_user_id' => $place->host_user_id,
        'booking_status' => BookingStatus::Completed->value,
        'start_date' => now()->subDays(4)->toDateString(),
        'end_date' => now()->subDays(3)->toDateString(),
        'guests' => 2, 'booking_price' => 100000, 'quantity' => 1, 'booking_amount' => 100000,
        'commission_rate' => 10, 'commission_amount' => 10000, 'vat_rate' => 15, 'vat_amount' => 15000,
        'total' => 115000, 'payout_status' => 'not_paid', 'confirmed_at' => now()->subDays(6),
    ], $attrs));
}

it('shows earnings totals and per-booking payout badges to the host', function (): void {
    $place = finPlace($this->host);
    // One settled (net SR 900), one still pending (net SR 900).
    finBooking($place, $this->guest, ['payout_status' => 'paid', 'paid_out_at' => now()]);
    $pending = finBooking($place, $this->guest);

    // The dashboard renders in Arabic by default — assert the Arabic badges.
    $this->actingAs($this->host, 'api')
        ->get('/financials')
        ->assertOk()
        // Cards: total 1,800 · paid 900 · pending 900.
        ->assertSee('1,800.00')
        ->assertSee('900.00')
        // Rows: reference + both badge states.
        ->assertSee($pending->reference)
        ->assertSee('قيد التحويل')   // pending payout badge
        ->assertSee('مدفوع');        // paid badge
});

it('never counts cancelled or pending-payment bookings as earnings', function (): void {
    $place = finPlace($this->host);
    finBooking($place, $this->guest, ['booking_status' => BookingStatus::CanceledByGuest->value]);
    finBooking($place, $this->guest, ['booking_status' => BookingStatus::PendingPayment->value]);

    $this->actingAs($this->host, 'api')
        ->get('/financials')
        ->assertOk()
        ->assertSee('0.00')
        ->assertSee('لا توجد حركات مالية بعد'); // Arabic empty state (default locale)
});

it('still renders the bank account form', function (): void {
    $this->actingAs($this->host, 'api')
        ->get('/financials')
        ->assertOk()
        ->assertSee('bank_account', false);
});

it('requires login', function (): void {
    $this->get('/financials')->assertRedirect();
});
