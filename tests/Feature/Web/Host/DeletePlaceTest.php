<?php

declare(strict_types=1);

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Enums\UserRole;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;

beforeEach(function (): void {
    $this->seed();
});

/** Active+Approved place owned by the given host. */
function deletablePlace(User $host): Place
{
    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Deletable place',
        'description' => 'x',
        'price' => 500,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ]);
}

it('lets the owner archive (soft-delete) their place', function (): void {
    $host = User::factory()->create(['phone' => '514000001']);
    $place = deletablePlace($host);

    $this->actingAs($host, 'api')
        ->delete("/my-places/{$place->id}")
        ->assertRedirect(route('user.places'))
        ->assertSessionHas('status');

    // Soft-deleted: gone from normal queries, still present with trashed.
    expect(Place::query()->find($place->id))->toBeNull()
        ->and(Place::withTrashed()->find($place->id)?->trashed())->toBeTrue();
});

it('hides a soft-deleted place from the host listing', function (): void {
    $host = User::factory()->create(['phone' => '514000002']);
    $place = deletablePlace($host);
    $place->delete();

    $this->actingAs($host, 'api')
        ->get('/my-places')
        ->assertOk()
        ->assertDontSee('Deletable place');
});

it("forbids deleting another host's place", function (): void {
    $owner = User::factory()->create(['phone' => '514000003']);
    $intruder = User::factory()->create(['phone' => '514000004']);
    $place = deletablePlace($owner);

    $this->actingAs($intruder, 'api')
        ->delete("/my-places/{$place->id}")
        ->assertForbidden();

    expect(Place::query()->find($place->id))->not->toBeNull();
});

it('lets an admin archive any place', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '599000010']);
    $host = User::factory()->create(['phone' => '514000005']);
    $place = deletablePlace($host);

    $this->actingAs($admin, 'api')
        ->delete("/my-places/{$place->id}")
        ->assertRedirect();

    expect(Place::query()->find($place->id))->toBeNull();
});
