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

function pendingPlace(User $host): Place
{
    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Pending place',
        'description' => 'x',
        'price' => 1000,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ]);
}

function pendingBooking(User $guest, Place $place, array $attrs = []): Booking
{
    return Booking::query()->create(array_merge([
        'place_id' => $place->id,
        'guest_user_id' => $guest->id,
        'host_user_id' => $place->host_user_id,
        'booking_status' => BookingStatus::PendingPayment->value,
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'guests' => 2,
        'nights' => 1,
        'stay_amount' => 100000,
        'commission_rate' => 10,
        'commission_amount' => 10000,
        'vat_rate' => 15,
        'vat_amount' => 15000,
        'total_amount' => 115000,
        'payout_status' => 'not_paid',
        'payment_url' => 'https://pay.moyasar.test/x',
        'expires_at' => now()->addMinutes(8),
    ], $attrs));
}

it('returns the guest\'s still-payable holds with the payment url, soonest-expiring first', function (): void {
    $host = User::factory()->create(['phone' => '519200001']);
    $guest = User::factory()->create(['phone' => '519200002']);
    $place = pendingPlace($host);

    $later = pendingBooking($guest, $place, ['expires_at' => now()->addMinutes(9)]);
    $sooner = pendingBooking($guest, $place, ['expires_at' => now()->addMinutes(2)]);

    $this->actingAs($guest, 'api')
        ->getJson('/api/bookings/pending')
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        // Soonest to expire first.
        ->assertJsonPath('data.items.0.id', $sooner->id)
        ->assertJsonPath('data.items.1.id', $later->id)
        ->assertJsonPath('data.items.0.status', 'pending_payment')
        ->assertJsonPath('data.items.0.payment.url', 'https://pay.moyasar.test/x')
        ->assertJsonStructure(['data' => ['items' => [['id', 'reference', 'expires_at', 'payment' => ['url']]]]]);
});

it('excludes confirmed, expired-hold, and other users\' bookings', function (): void {
    $host = User::factory()->create(['phone' => '519200003']);
    $guest = User::factory()->create(['phone' => '519200004']);
    $other = User::factory()->create(['phone' => '519200005']);
    $place = pendingPlace($host);

    $payable = pendingBooking($guest, $place);
    pendingBooking($guest, $place, ['booking_status' => BookingStatus::Confirmed->value]);        // already paid
    pendingBooking($guest, $place, ['expires_at' => now()->subMinute()]);                          // hold lapsed
    pendingBooking($other, $place);                                                                // not mine

    $this->actingAs($guest, 'api')
        ->getJson('/api/bookings/pending')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $payable->id);
});

it('requires authentication', function (): void {
    $this->getJson('/api/bookings/pending')->assertStatus(401);
});
