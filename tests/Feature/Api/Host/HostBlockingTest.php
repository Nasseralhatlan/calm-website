<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\Booking;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceBlocking;
use App\Models\PlaceType;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed();
    Carbon::setTestNow('2026-07-01 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function blkPlace(User $host): Place
{
    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Blk '.fake()->unique()->numerify('####'),
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

it('blocks a date range and it shows on the calendar as manual_block', function (): void {
    $host = User::factory()->create(['phone' => '513000001']);
    $place = blkPlace($host);

    $this->actingAs($host, 'api')
        ->postJson("/api/host/places/{$place->id}/blockings", [
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-11',
            'reason' => 'Maintenance',
        ])
        ->assertCreated()
        ->assertJsonPath('data.start_date', '2026-07-10')
        ->assertJsonPath('data.end_date', '2026-07-11')
        ->assertJsonPath('data.source', 'manual')
        ->assertJsonPath('data.reason', 'Maintenance');

    expect(PlaceBlocking::query()->where('place_id', $place->id)->count())->toBe(1);

    // The calendar we built now reflects it.
    $this->actingAs($host, 'api')
        ->getJson("/api/host/calendar?from=2026-07-01&to=2026-07-31&place_id={$place->id}")
        ->assertOk()
        ->assertJsonPath('data.days.2026-07-10.manual_block', true)
        ->assertJsonPath('data.days.2026-07-11.manual_block', true);
});

it('lists upcoming blocks and excludes fully-past ones', function (): void {
    $host = User::factory()->create(['phone' => '513000002']);
    $place = blkPlace($host);

    PlaceBlocking::query()->create(['place_id' => $place->id, 'start_date' => '2026-07-20', 'end_date' => '2026-07-21']);
    PlaceBlocking::query()->create(['place_id' => $place->id, 'start_date' => '2026-06-01', 'end_date' => '2026-06-02']); // past

    $this->actingAs($host, 'api')
        ->getJson("/api/host/places/{$place->id}/blockings")
        ->assertOk()
        ->assertJsonPath('data.place_id', $place->id)
        ->assertJsonCount(1, 'data.blockings')
        ->assertJsonPath('data.blockings.0.start_date', '2026-07-20');
});

it('removes a block', function (): void {
    $host = User::factory()->create(['phone' => '513000003']);
    $place = blkPlace($host);
    $blocking = PlaceBlocking::query()->create(['place_id' => $place->id, 'start_date' => '2026-07-10', 'end_date' => '2026-07-11']);

    $this->actingAs($host, 'api')
        ->deleteJson("/api/host/places/{$place->id}/blockings/{$blocking->id}")
        ->assertOk();

    expect(PlaceBlocking::query()->whereKey($blocking->id)->exists())->toBeFalse();
});

it('allows blocking a range that already has a booking (booking untouched)', function (): void {
    $host = User::factory()->create(['phone' => '513000004']);
    $guest = User::factory()->create(['phone' => '513000005']);
    $place = blkPlace($host);

    $booking = Booking::query()->create([
        'place_id' => $place->id, 'guest_user_id' => $guest->id, 'host_user_id' => $host->id,
        'booking_status' => BookingStatus::Confirmed->value,
        'start_date' => '2026-07-15', 'end_date' => '2026-07-16',
        'guests' => 2, 'nights' => 1, 'stay_amount' => 100000,
        'commission_rate' => 10, 'commission_amount' => 10000, 'guest_vat_rate' => 15, 'guest_vat_amount' => 15000,
        'guest_total' => 115000, 'payout_status' => 'not_paid', 'confirmed_at' => now(),
    ]);

    $this->actingAs($host, 'api')
        ->postJson("/api/host/places/{$place->id}/blockings", ['start_date' => '2026-07-15', 'end_date' => '2026-07-16'])
        ->assertCreated();

    expect($booking->fresh()->booking_status)->toBe(BookingStatus::Confirmed);
});

it('validates the date window', function (): void {
    $host = User::factory()->create(['phone' => '513000006']);
    $place = blkPlace($host);

    // end before start
    $this->actingAs($host, 'api')
        ->postJson("/api/host/places/{$place->id}/blockings", ['start_date' => '2026-07-11', 'end_date' => '2026-07-10'])
        ->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['end_date']]]);

    // past start
    $this->actingAs($host, 'api')
        ->postJson("/api/host/places/{$place->id}/blockings", ['start_date' => '2026-06-01', 'end_date' => '2026-06-02'])
        ->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['start_date']]]);
});

it('forbids managing another host\'s place, and 404s a mismatched blocking', function (): void {
    $host = User::factory()->create(['phone' => '513000007']);
    $otherHost = User::factory()->create(['phone' => '513000008']);
    $otherPlace = blkPlace($otherHost);
    $myPlace = blkPlace($host);

    // Another host's place → 403 on each verb.
    $this->actingAs($host, 'api')->getJson("/api/host/places/{$otherPlace->id}/blockings")->assertForbidden();
    $this->actingAs($host, 'api')
        ->postJson("/api/host/places/{$otherPlace->id}/blockings", ['start_date' => '2026-07-10', 'end_date' => '2026-07-11'])
        ->assertForbidden();

    // A blocking that belongs to a different place → 404 (scoped binding).
    $foreignBlocking = PlaceBlocking::query()->create(['place_id' => $otherPlace->id, 'start_date' => '2026-07-10', 'end_date' => '2026-07-11']);
    $this->actingAs($host, 'api')
        ->deleteJson("/api/host/places/{$myPlace->id}/blockings/{$foreignBlocking->id}")
        ->assertNotFound();
});

it('requires authentication', function (): void {
    $place = blkPlace(User::factory()->create(['phone' => '513000009']));
    $this->getJson("/api/host/places/{$place->id}/blockings")->assertStatus(401);
    $this->postJson("/api/host/places/{$place->id}/blockings", [])->assertStatus(401);
});
