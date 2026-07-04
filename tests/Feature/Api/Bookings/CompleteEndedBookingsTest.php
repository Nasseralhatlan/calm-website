<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Jobs\CompleteEndedBookings;
use App\Models\Booking;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;
use App\Services\Booking\BookingService;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->seed();
});

function completionPlace(array $attrs = []): Place
{
    $host = User::factory()->create(['phone' => '54'.fake()->unique()->numerify('#######')]);

    return Place::query()->create(array_merge([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Stay '.fake()->unique()->numerify('####'),
        'description' => 'Desc.',
        'price' => 1000,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ], $attrs));
}

function completionBooking(array $attrs = []): Booking
{
    $place = completionPlace();

    return Booking::query()->create(array_merge([
        'place_id' => $place->id,
        'guest_user_id' => User::factory()->create(['phone' => '55'.fake()->unique()->numerify('#######')])->id,
        'host_user_id' => $place->host_user_id,
        'booking_status' => BookingStatus::Confirmed->value,
        'start_date' => '2026-06-18',
        'end_date' => '2026-06-19',
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'guests' => 2,
        'booking_price' => 100000,
        'quantity' => 1,
        'host_gross_amount' => 100000,
        'commission_rate' => 10,
        'commission_amount_ex_vat' => 10000,
        'guest_vat_rate' => 15,
        'guest_vat_amount' => 15000,
        'guest_total' => 115000,
        'payout_status' => 'not_paid',
    ], $attrs));
}

function runCompletionSweep(): void
{
    (new CompleteEndedBookings)->handle(app(BookingService::class));
}

it('completes an overnight stay after its checkout the morning after end_date', function (): void {
    // in 15:00 / out 12:00, end_date the 19th → checkout is the 20th at noon.
    $booking = completionBooking(['end_date' => '2026-06-19']);

    // Just before checkout (20th 11:00) → still confirmed.
    $this->travelTo(CarbonImmutable::parse('2026-06-20 11:00:00'));
    runCompletionSweep();
    expect($booking->refresh()->booking_status)->toBe(BookingStatus::Confirmed);

    // Just after checkout (20th 13:00) → completed.
    $this->travelTo(CarbonImmutable::parse('2026-06-20 13:00:00'));
    runCompletionSweep();
    expect($booking->refresh()->booking_status)->toBe(BookingStatus::Completed);
});

it('treats an early-AM checkout as the day after end_date', function (): void {
    // in 15:00 / out 03:00, end_date the 19th → checkout is the 20th at 03:00.
    $booking = completionBooking(['check_out_time' => '03:00', 'end_date' => '2026-06-19']);

    // 20th 02:00 — checkout hasn't arrived yet.
    $this->travelTo(CarbonImmutable::parse('2026-06-20 02:00:00'));
    runCompletionSweep();
    expect($booking->refresh()->booking_status)->toBe(BookingStatus::Confirmed);

    // 20th 04:00 — past the 3 AM checkout.
    $this->travelTo(CarbonImmutable::parse('2026-06-20 04:00:00'));
    runCompletionSweep();
    expect($booking->refresh()->booking_status)->toBe(BookingStatus::Completed);
});

it('completes a same-day (day-use) stay on end_date itself', function (): void {
    // checkout_next_day = false → checkout is on end_date (the 19th) at 18:00.
    $booking = completionBooking([
        'start_date' => '2026-06-19',
        'end_date' => '2026-06-19',
        'check_in_time' => '09:00',
        'check_out_time' => '18:00',
        'checkout_next_day' => false,
    ]);

    // 19th 17:00 — still mid-stay.
    $this->travelTo(CarbonImmutable::parse('2026-06-19 17:00:00'));
    runCompletionSweep();
    expect($booking->refresh()->booking_status)->toBe(BookingStatus::Confirmed);

    // 19th 19:00 — past checkout.
    $this->travelTo(CarbonImmutable::parse('2026-06-19 19:00:00'));
    runCompletionSweep();
    expect($booking->refresh()->booking_status)->toBe(BookingStatus::Completed);
});

it('only touches confirmed bookings', function (): void {
    $pending = completionBooking(['booking_status' => BookingStatus::PendingPayment->value, 'end_date' => '2026-06-01']);
    $expired = completionBooking(['booking_status' => BookingStatus::Expired->value, 'end_date' => '2026-06-01']);
    $confirmed = completionBooking(['booking_status' => BookingStatus::Confirmed->value, 'end_date' => '2026-06-01']);

    $this->travelTo(CarbonImmutable::parse('2026-06-20 13:00:00'));
    runCompletionSweep();

    expect($pending->refresh()->booking_status)->toBe(BookingStatus::PendingPayment)
        ->and($expired->refresh()->booking_status)->toBe(BookingStatus::Expired)
        ->and($confirmed->refresh()->booking_status)->toBe(BookingStatus::Completed);
});

it('reports the number of bookings it completed', function (): void {
    completionBooking(['end_date' => '2026-06-01']);
    completionBooking(['end_date' => '2026-06-01']);
    completionBooking(['end_date' => '2026-12-31']); // far future — not yet ended

    $this->travelTo(CarbonImmutable::parse('2026-06-20 13:00:00'));

    expect(app(BookingService::class)->completeEndedStays())->toBe(2);
});
