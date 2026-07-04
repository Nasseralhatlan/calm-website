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
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed();
    // Freeze the clock so check-in/checkout time comparisons are deterministic.
    Carbon::setTestNow('2026-06-29 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function hlPlace(User $host): Place
{
    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'HL '.fake()->unique()->numerify('####'),
        'description' => 'Desc.',
        'price' => 1000,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'checkout_next_day' => false,
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ]);
}

function hlBooking(Place $place, User $guest, array $attrs): Booking
{
    return Booking::query()->create(array_merge([
        'place_id' => $place->id,
        'guest_user_id' => $guest->id,
        'host_user_id' => $place->host_user_id,
        'booking_status' => BookingStatus::Confirmed->value,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'checkout_next_day' => false,
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
        'confirmed_at' => now()->subDay(),
    ], $attrs));
}

it('buckets by real check-in/checkout instants — today + checked-in counts as ongoing', function (): void {
    // now frozen at 2026-06-29 12:00.
    $host = User::factory()->create(['phone' => '518000001']);
    $guest = User::factory()->create(['phone' => '518000002']);
    $place = hlPlace($host);

    // ongoing #1: started yesterday, checkout tomorrow → in-house now.
    $ongoingPast = hlBooking($place, $guest, [
        'start_date' => '2026-06-28', 'check_in_time' => '15:00',
        'end_date' => '2026-06-30', 'check_out_time' => '12:00',
    ]);
    // ongoing #2: starts TODAY but check-in time (09:00) already passed → ongoing, not today.
    $ongoingToday = hlBooking($place, $guest, [
        'start_date' => '2026-06-29', 'check_in_time' => '09:00',
        'end_date' => '2026-06-30', 'check_out_time' => '12:00',
    ]);
    // today: starts today, check-in (15:00) still ahead of now → arriving later today.
    $todayLater = hlBooking($place, $guest, [
        'start_date' => '2026-06-29', 'check_in_time' => '15:00',
        'end_date' => '2026-06-30', 'check_out_time' => '12:00',
    ]);
    // upcoming: future arrival within 7 days.
    $upcoming = hlBooking($place, $guest, [
        'start_date' => '2026-07-02', 'end_date' => '2026-07-03',
    ]);

    // ── excluded ──
    hlBooking($place, $guest, ['start_date' => '2026-07-10', 'end_date' => '2026-07-11']);                 // beyond 7 days
    hlBooking($place, $guest, ['start_date' => '2026-06-20', 'end_date' => '2026-06-22']);                 // checked out → not ongoing
    hlBooking($place, $guest, ['booking_status' => BookingStatus::Completed->value, 'start_date' => '2026-06-29', 'check_in_time' => '09:00', 'end_date' => '2026-06-30']); // not confirmed

    $res = $this->actingAs($host, 'api')
        ->getJson('/api/host/bookings/highlights')
        ->assertOk()
        ->assertJsonPath('data.counts.ongoing', 2)
        ->assertJsonPath('data.counts.today', 1)
        ->assertJsonPath('data.counts.upcoming', 1)
        ->assertJsonPath('data.today.0.id', $todayLater->id)
        ->assertJsonPath('data.upcoming.0.id', $upcoming->id)
        // Host gets the guest's name + avatar, but NOT their phone.
        ->assertJsonPath('data.today.0.guest.name', $guest->name)
        ->assertJsonMissingPath('data.today.0.guest.phone')
        ->assertJsonStructure(['data' => [
            'ongoing', 'today', 'upcoming',
            'today' => [['guest' => ['id', 'name', 'avatar_url']]],
            'counts' => ['ongoing', 'today', 'upcoming'],
        ]]);

    // Both ongoing bookings (past-start and today-checked-in) are present.
    $ongoingIds = collect($res->json('data.ongoing'))->pluck('id')->all();
    expect($ongoingIds)->toContain($ongoingPast->id)->toContain($ongoingToday->id);
});

it('only returns the authenticated host\'s bookings', function (): void {
    $host = User::factory()->create(['phone' => '518000010']);
    $otherHost = User::factory()->create(['phone' => '518000011']);
    $guest = User::factory()->create(['phone' => '518000012']);

    hlBooking(hlPlace($otherHost), $guest, [
        'start_date' => '2026-06-29', 'check_in_time' => '15:00', 'end_date' => '2026-06-30',
    ]);

    $this->actingAs($host, 'api')
        ->getJson('/api/host/bookings/highlights')
        ->assertOk()
        ->assertJsonPath('data.counts.today', 0);
});

it('requires authentication', function (): void {
    $this->getJson('/api/host/bookings/highlights')->assertStatus(401);
});
