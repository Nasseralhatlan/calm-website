<?php

declare(strict_types=1);

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;

beforeEach(function (): void {
    $this->seed();
});

function favPlace(User $host, array $attrs = []): Place
{
    return Place::query()->create(array_merge([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Fav '.fake()->unique()->numerify('place-####'),
        'description' => 'Desc.',
        'price' => 500,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ], $attrs));
}

it('returns the viewer\'s liked visible places, newest-liked first, is_liked true', function (): void {
    $host = User::factory()->create(['phone' => '518000001']);
    $viewer = User::factory()->create(['phone' => '518000002']);
    $a = favPlace($host, ['title' => 'Place A']);
    $b = favPlace($host, ['title' => 'Place B']);

    $this->actingAs($viewer, 'api')->postJson("/api/places/{$a->id}/like")->assertOk();
    $this->travel(1)->minutes();
    $this->actingAs($viewer, 'api')->postJson("/api/places/{$b->id}/like")->assertOk();

    $this->actingAs($viewer, 'api')
        ->getJson('/api/favorites')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 2)
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.id', $b->id) // most recently liked first
        ->assertJsonPath('data.items.1.id', $a->id)
        ->assertJsonPath('data.items.0.is_liked', true)
        ->assertJsonPath('data.items.1.is_liked', true);
});

it('excludes places the viewer has not liked', function (): void {
    $host = User::factory()->create(['phone' => '518000003']);
    $viewer = User::factory()->create(['phone' => '518000004']);
    $liked = favPlace($host);
    favPlace($host); // not liked

    $this->actingAs($viewer, 'api')->postJson("/api/places/{$liked->id}/like")->assertOk();

    $this->actingAs($viewer, 'api')
        ->getJson('/api/favorites')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.items.0.id', $liked->id);
});

it('excludes a liked place that is no longer visible', function (): void {
    $host = User::factory()->create(['phone' => '518000005']);
    $viewer = User::factory()->create(['phone' => '518000006']);
    $visible = favPlace($host);
    $hidden = favPlace($host, ['review_status' => PlaceReviewStatus::Draft->value]);

    $this->actingAs($viewer, 'api')->postJson("/api/places/{$visible->id}/like")->assertOk();
    // Attach the hidden one directly (the like endpoint would 404 on it).
    $viewer->likedPlaces()->attach($hidden->id);

    $this->actingAs($viewer, 'api')
        ->getJson('/api/favorites')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.items.0.id', $visible->id);
});

it('paginates the favorites feed', function (): void {
    config(['pagination.per_page' => 1]); // page size is server-controlled now, not ?per_page=
    $host = User::factory()->create(['phone' => '518000007']);
    $viewer = User::factory()->create(['phone' => '518000008']);
    $a = favPlace($host);
    $b = favPlace($host);

    $this->actingAs($viewer, 'api')->postJson("/api/places/{$a->id}/like")->assertOk();
    $this->travel(1)->minutes();
    $this->actingAs($viewer, 'api')->postJson("/api/places/{$b->id}/like")->assertOk();

    $this->actingAs($viewer, 'api')
        ->getJson('/api/favorites')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $b->id)
        ->assertJsonPath('data.pagination.per_page', 1)
        ->assertJsonPath('data.pagination.total', 2)
        ->assertJsonPath('data.pagination.last_page', 2)
        ->assertJsonPath('data.pagination.has_more', true);

    $this->actingAs($viewer, 'api')
        ->getJson('/api/favorites?page=2')
        ->assertOk()
        ->assertJsonPath('data.items.0.id', $a->id)
        ->assertJsonPath('data.pagination.page', 2)
        ->assertJsonPath('data.pagination.has_more', false);
});

it('requires authentication to fetch favorites', function (): void {
    $this->getJson('/api/favorites')->assertStatus(401);
});

it('returns 404 when liking a non-visible place', function (): void {
    $host = User::factory()->create(['phone' => '518000009']);
    $viewer = User::factory()->create(['phone' => '518000010']);
    $draft = favPlace($host, ['review_status' => PlaceReviewStatus::Draft->value]);

    $this->actingAs($viewer, 'api')
        ->postJson("/api/places/{$draft->id}/like")
        ->assertStatus(404);

    expect($viewer->likedPlaces()->count())->toBe(0);
});
