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

function bookablePlace(User $host): Place
{
    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Lakeview Chalet',
        'price' => 1000,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ]);
}

function confirmedBooking(User $guest, Place $place): Booking
{
    return Booking::query()->create([
        'place_id' => $place->id,
        'guest_user_id' => $guest->id,
        'host_user_id' => $place->host_user_id,
        'booking_status' => BookingStatus::Confirmed->value,
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeek()->addDay()->toDateString(),
        'guests' => 2,
        'nights' => 2,
        'stay_amount' => 200000,
        'commission_rate' => 15,
        'commission_amount' => 30000,
        'vat_rate' => 15,
        'vat_amount' => 30000,
        'total_amount' => 230000,
        'payout_status' => 'not_paid',
        'confirmed_at' => now(),
    ]);
}

it('keeps bookings and still resolves the place after the place is archived', function (): void {
    $host = User::factory()->create(['phone' => '518000001']);
    $guest = User::factory()->create(['phone' => '518000002']);
    $place = bookablePlace($host);
    $booking = confirmedBooking($guest, $place);

    $place->delete(); // soft-delete (archive)

    // Booking survives — soft delete did not cascade.
    expect(Booking::query()->find($booking->id))->not->toBeNull();

    // And the place still resolves on the booking (withTrashed), readable.
    $fresh = Booking::query()->find($booking->id);
    expect($fresh->place)->not->toBeNull()
        ->and($fresh->place->title)->toBe('Lakeview Chalet')
        ->and($fresh->place->trashed())->toBeTrue();
});

it('still returns the place block in the guest bookings API after archive', function (): void {
    $host = User::factory()->create(['phone' => '518000003']);
    $guest = User::factory()->create(['phone' => '518000004']);
    $place = bookablePlace($host);
    confirmedBooking($guest, $place);
    $place->delete();

    $this->withHeader('Authorization', 'Bearer '.auth('api')->login($guest))
        ->getJson('/api/bookings')
        ->assertOk()
        ->assertJsonPath('data.items.0.place.title', 'Lakeview Chalet');
});

it('blocks new bookings on an archived place (route 404)', function (): void {
    $host = User::factory()->create(['phone' => '518000005']);
    $guest = User::factory()->create(['phone' => '518000006']);
    $place = bookablePlace($host);
    $place->delete();

    $this->withHeader('Authorization', 'Bearer '.auth('api')->login($guest))
        ->postJson("/api/places/{$place->id}/bookings", [
            'check_in' => now()->addWeek()->toDateString(),
            'check_out' => now()->addWeek()->addDay()->toDateString(),
            'guests' => 2,
        ])
        ->assertNotFound();
});
