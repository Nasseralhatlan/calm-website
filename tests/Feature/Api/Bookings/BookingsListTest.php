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

function listPlace(User $host, array $attrs = []): Place
{
    return Place::query()->create(array_merge([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'List '.fake()->unique()->numerify('chalet-####'),
        'description' => 'Desc.',
        'price' => 1000,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ], $attrs));
}

function listBooking(Place $place, User $guest, array $attrs = []): Booking
{
    return Booking::query()->create(array_merge([
        'place_id' => $place->id,
        'guest_user_id' => $guest->id,
        'host_user_id' => $place->host_user_id,
        'booking_status' => BookingStatus::Confirmed->value,
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'guests' => 2,
        'booking_price' => 100000,
        'quantity' => 2,
        'booking_amount' => 200000,
        'commission_rate' => 10,
        'commission_amount' => 20000,
        'vat_rate' => 15,
        'vat_amount' => 30000,
        'total' => 230000,
        'payout_status' => 'not_paid',
    ], $attrs));
}

it('lists the guest\'s bookings, newest first, with place details + price + status', function (): void {
    $guest = User::factory()->create(['phone' => '519000001']);
    $host = User::factory()->create(['phone' => '519000002']);
    $placeA = listPlace($host, ['title' => 'Older Stay']);
    $placeB = listPlace($host, ['title' => 'Newer Stay']);

    $older = listBooking($placeA, $guest, ['booking_status' => BookingStatus::Expired->value]);
    $this->travel(1)->minutes();
    $newer = listBooking($placeB, $guest);

    $this->actingAs($guest, 'api')
        ->getJson('/api/bookings')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 2)
        ->assertJsonCount(2, 'data.items')
        // Newest first.
        ->assertJsonPath('data.items.0.id', $newer->id)
        ->assertJsonPath('data.items.1.id', $older->id)
        // Status, dates, price, and place details on each item.
        ->assertJsonPath('data.items.0.status', 'confirmed')
        ->assertJsonPath('data.items.0.start_date', now()->addDays(3)->toDateString())
        ->assertJsonPath('data.items.0.pricing.total', 2300)
        ->assertJsonPath('data.items.0.place.title', 'Newer Stay')
        ->assertJsonStructure(['data' => ['items' => [[
            'id', 'status', 'start_date', 'end_date', 'guests',
            'pricing' => ['total', 'total_minor'],
            'place' => ['id', 'title', 'cover_photo_url', 'type', 'city'],
        ]]]]);
});

it('only returns the authenticated guest\'s bookings', function (): void {
    $guest = User::factory()->create(['phone' => '519000003']);
    $other = User::factory()->create(['phone' => '519000004']);
    $host = User::factory()->create(['phone' => '519000005']);
    $place = listPlace($host);

    listBooking($place, $guest);
    listBooking($place, $other);

    $this->actingAs($guest, 'api')
        ->getJson('/api/bookings')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1);
});

it('paginates the bookings list', function (): void {
    $guest = User::factory()->create(['phone' => '519000006']);
    $host = User::factory()->create(['phone' => '519000007']);
    $place = listPlace($host);

    listBooking($place, $guest);
    $this->travel(1)->minutes();
    $latest = listBooking($place, $guest);

    $this->actingAs($guest, 'api')
        ->getJson('/api/bookings?per_page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $latest->id)
        ->assertJsonPath('data.pagination.per_page', 1)
        ->assertJsonPath('data.pagination.total', 2)
        ->assertJsonPath('data.pagination.has_more', true);
});

it('requires authentication to list bookings', function (): void {
    $this->getJson('/api/bookings')->assertStatus(401);
});
