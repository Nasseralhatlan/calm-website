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

function isHostPlace(User $host, array $attrs = []): Place
{
    return Place::query()->create(array_merge([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Host Stay '.fake()->unique()->numerify('####'),
        'description' => 'Desc.',
        'price' => 1000,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ], $attrs));
}

function isHostBooking(Place $place, User $guest, string $status): Booking
{
    return Booking::query()->create([
        'place_id' => $place->id,
        'guest_user_id' => $guest->id,
        'host_user_id' => $place->host_user_id,
        'booking_status' => $status,
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'guests' => 2,
        'nights' => 1,
        'stay_amount' => 100000,
        'commission_rate' => 10,
        'commission_amount' => 10000,
        'guest_vat_rate' => 15,
        'guest_vat_amount' => 15000,
        'guest_total' => 115000,
        'payout_status' => 'not_paid',
    ]);
}

it('is a host with an active (or draft) place', function (): void {
    $host = User::factory()->create(['phone' => '511000001']);
    isHostPlace($host, ['review_status' => PlaceReviewStatus::Draft->value]);

    expect($host->isHost())->toBeTrue();
});

it('is not a host with no place at all', function (): void {
    $user = User::factory()->create(['phone' => '511000002']);

    expect($user->isHost())->toBeFalse();
});

it('is not a host when the only place is soft-deleted and there are no bookings', function (): void {
    $host = User::factory()->create(['phone' => '511000003']);
    isHostPlace($host)->delete(); // soft delete

    expect($host->fresh()->isHost())->toBeFalse();
});

it('stays a host after deleting the place when a confirmed booking remains', function (): void {
    $host = User::factory()->create(['phone' => '511000004']);
    $guest = User::factory()->create(['phone' => '511000005']);
    $place = isHostPlace($host);
    isHostBooking($place, $guest, BookingStatus::Confirmed->value);

    $place->delete(); // host removes the listing

    expect($host->fresh()->isHost())->toBeTrue();
});

it('a completed booking on a deleted place also keeps host access', function (): void {
    $host = User::factory()->create(['phone' => '511000006']);
    $guest = User::factory()->create(['phone' => '511000007']);
    $place = isHostPlace($host);
    isHostBooking($place, $guest, BookingStatus::Completed->value);
    $place->delete();

    expect($host->fresh()->isHost())->toBeTrue();
});

it('an abandoned (pending/expired) booking on a deleted place does not keep host access', function (): void {
    $host = User::factory()->create(['phone' => '511000008']);
    $guest = User::factory()->create(['phone' => '511000009']);
    $place = isHostPlace($host);
    isHostBooking($place, $guest, BookingStatus::PendingPayment->value);
    isHostBooking($place, $guest, BookingStatus::Expired->value);
    $place->delete();

    expect($host->fresh()->isHost())->toBeFalse();
});
