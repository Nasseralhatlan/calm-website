<?php

declare(strict_types=1);

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Enums\UserRole;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceBlocking;
use App\Models\PlaceType;
use App\Models\User;

beforeEach(function (): void {
    $this->seed();
});

/** An Active+Approved place owned by the given host. */
function hostOwnedPlace(User $host, array $attrs = []): Place
{
    return Place::query()->create(array_merge([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Host place',
        'description' => 'Desc.',
        'price' => 600,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ], $attrs));
}

it('shows the availability manager to the place owner', function (): void {
    $host = User::factory()->create(['phone' => '512000001']);
    $place = hostOwnedPlace($host, ['title' => 'Lakeview Chalet']);

    $this->actingAs($host, 'api')
        ->get("/my-places/{$place->id}/availability")
        ->assertOk()
        ->assertSee('Lakeview Chalet');
});

it('lets the host block a date range', function (): void {
    $host = User::factory()->create(['phone' => '512000002']);
    $place = hostOwnedPlace($host);

    $start = now()->addDays(3)->toDateString();
    $end = now()->addDays(6)->toDateString();

    $this->actingAs($host, 'api')
        ->post("/my-places/{$place->id}/blockings", [
            'start_date' => $start,
            'end_date' => $end,
            'reason' => 'Personal use',
        ])
        ->assertRedirect(route('host.places.availability', $place))
        ->assertSessionHas('status');

    $blocking = $place->blockings()->first();
    expect($blocking)->not->toBeNull();
    expect($blocking->start_date->toDateString())->toBe($start);
    expect($blocking->end_date->toDateString())->toBe($end);
    expect($blocking->reason)->toBe('Personal use');
});

it('rejects blocking dates in the past', function (): void {
    $host = User::factory()->create(['phone' => '512000003']);
    $place = hostOwnedPlace($host);

    $this->actingAs($host, 'api')
        ->post("/my-places/{$place->id}/blockings", [
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ])
        ->assertSessionHasErrors('start_date');

    expect(PlaceBlocking::query()->count())->toBe(0);
});

it('rejects an end date before the start date', function (): void {
    $host = User::factory()->create(['phone' => '512000004']);
    $place = hostOwnedPlace($host);

    $this->actingAs($host, 'api')
        ->post("/my-places/{$place->id}/blockings", [
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ])
        ->assertSessionHasErrors('end_date');

    expect(PlaceBlocking::query()->count())->toBe(0);
});

it('lets the host unblock a range', function (): void {
    $host = User::factory()->create(['phone' => '512000005']);
    $place = hostOwnedPlace($host);
    $blocking = $place->blockings()->create([
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
    ]);

    $this->actingAs($host, 'api')
        ->delete("/my-places/{$place->id}/blockings/{$blocking->id}")
        ->assertRedirect(route('host.places.availability', $place))
        ->assertSessionHas('status');

    $this->assertDatabaseMissing('place_blockings', ['id' => $blocking->id]);
});

it('forbids a host from managing another host\'s place', function (): void {
    $owner = User::factory()->create(['phone' => '512000006']);
    $intruder = User::factory()->create(['phone' => '512000007']);
    $place = hostOwnedPlace($owner);

    $this->actingAs($intruder, 'api')
        ->get("/my-places/{$place->id}/availability")
        ->assertForbidden();

    $this->actingAs($intruder, 'api')
        ->post("/my-places/{$place->id}/blockings", [
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ])
        ->assertForbidden();

    expect(PlaceBlocking::query()->count())->toBe(0);
});

it('404s when unblocking a blocking that belongs to another place', function (): void {
    $host = User::factory()->create(['phone' => '512000008']);
    $placeA = hostOwnedPlace($host);
    $placeB = hostOwnedPlace($host);
    $blockingOnB = $placeB->blockings()->create([
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
    ]);

    // Route scoped binding: blocking must belong to {place} in the URL.
    $this->actingAs($host, 'api')
        ->delete("/my-places/{$placeA->id}/blockings/{$blockingOnB->id}")
        ->assertNotFound();

    $this->assertDatabaseHas('place_blockings', ['id' => $blockingOnB->id]);
});

it('lets an admin manage any host\'s place availability', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '599000001']);
    $host = User::factory()->create(['phone' => '512000009']);
    $place = hostOwnedPlace($host);

    $this->actingAs($admin, 'api')
        ->post("/my-places/{$place->id}/blockings", [
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('place_blockings', ['place_id' => $place->id]);
});
