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
});

function checkoutBookingModel(array $attrs = []): Booking
{
    return new Booking(array_merge([
        'end_date' => '2026-06-19',
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'checkout_next_day' => true,
    ], $attrs));
}

it('checkoutAt: overnight stay checks out the morning after end_date', function (): void {
    expect(checkoutBookingModel()->checkoutAt()->format('Y-m-d H:i'))->toBe('2026-06-20 12:00');
});

it('checkoutAt: same-day stay checks out on end_date itself', function (): void {
    expect(checkoutBookingModel(['checkout_next_day' => false])->checkoutAt()->format('Y-m-d H:i'))
        ->toBe('2026-06-19 12:00');
});

it('checkoutAt: early-AM checkout still lands the next day', function (): void {
    expect(checkoutBookingModel(['check_out_time' => '03:00'])->checkoutAt()->format('Y-m-d H:i'))
        ->toBe('2026-06-20 03:00');
});

it('checkoutAt: null checkout time falls back to start of the checkout day', function (): void {
    expect(checkoutBookingModel(['check_out_time' => null])->checkoutAt()->format('Y-m-d H:i'))
        ->toBe('2026-06-20 00:00');
});

it('exposes checkout_next_day + checkout_at on the bookings API', function (): void {
    $host = User::factory()->create(['phone' => '519300001']);
    $guest = User::factory()->create(['phone' => '519300002']);
    $place = Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Co place', 'description' => 'x', 'price' => 1000,
        'check_in_time' => '15:00', 'check_out_time' => '12:00', 'max_guests' => 4,
        'status' => PlaceStatus::Active->value, 'review_status' => PlaceReviewStatus::Approved->value,
    ]);

    Booking::query()->create([
        'place_id' => $place->id, 'guest_user_id' => $guest->id, 'host_user_id' => $host->id,
        'booking_status' => BookingStatus::Confirmed->value,
        'start_date' => '2026-06-18', 'end_date' => '2026-06-19',
        'check_in_time' => '15:00', 'check_out_time' => '12:00', 'checkout_next_day' => true,
        'guests' => 2, 'nights' => 1, 'stay_amount' => 100000,
        'commission_rate' => 10, 'commission_amount' => 10000, 'guest_vat_rate' => 15, 'guest_vat_amount' => 15000,
        'guest_total' => 115000, 'payout_status' => 'not_paid',
    ]);

    $this->actingAs($guest, 'api')
        ->getJson('/api/bookings')
        ->assertOk()
        ->assertJsonPath('data.items.0.checkout_next_day', true)
        ->assertJsonPath('data.items.0.checkout_at', '2026-06-20T12:00:00+00:00');

    // The place detail exposes the flag too.
    $this->getJson("/api/places/{$place->id}")
        ->assertOk()
        ->assertJsonPath('data.checkout_next_day', true);
});
