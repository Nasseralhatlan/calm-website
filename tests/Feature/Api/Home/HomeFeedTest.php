<?php

declare(strict_types=1);

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\City;
use App\Models\CityArea;
use App\Models\Country;
use App\Models\Place;
use App\Models\PlaceList;
use App\Models\PlaceReview;
use App\Models\PlaceType;
use App\Models\User;

beforeEach(function (): void {
    // Seed the geo + place-type base data so the API endpoints have something
    // real to return. Skips bookings/places/lists — those are constructed
    // per-test below.
    $this->seed();
});

/**
 * Fast factory-free helper: insert an Active+Approved place owned by $host
 * in a real city area, with sensible defaults. Per-test overrides via $attrs.
 */
function makeVisiblePlace(User $host, array $attrs = []): Place
{
    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => $attrs['title'] ?? 'Test place '.fake()->word(),
        'description' => 'desc',
        'price' => $attrs['price'] ?? 500,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'status' => $attrs['status'] ?? PlaceStatus::Active->value,
        'review_status' => $attrs['review_status'] ?? PlaceReviewStatus::Approved->value,
    ]);
}

it('returns active countries through GET /api/countries', function (): void {
    $activeCount = Country::query()->active()->count();

    $this->getJson('/api/countries')
        ->assertOk()
        ->assertJsonPath('status', 200)
        ->assertJsonCount($activeCount, 'data')
        ->assertJsonStructure([
            'status', 'message', 'data' => [['id', 'country_code', 'dial_code', 'name_en', 'name_ar', 'avatar']],
        ]);
});

it('returns active cities with their areas through GET /api/cities', function (): void {
    $activeCount = City::query()->active()->count();

    $this->getJson('/api/cities')
        ->assertOk()
        ->assertJsonPath('status', 200)
        ->assertJsonCount($activeCount, 'data')
        ->assertJsonStructure([
            'status', 'message', 'data' => [
                ['id', 'name_en', 'name_ar', 'avatar', 'country_id', 'areas' => [['id', 'name_en', 'name_ar']]],
            ],
        ]);
});

it('returns active place types through GET /api/place-types', function (): void {
    $activeCount = PlaceType::query()->active()->count();

    $this->getJson('/api/place-types')
        ->assertOk()
        ->assertJsonCount($activeCount, 'data')
        ->assertJsonStructure([
            'status', 'message', 'data' => [['id', 'name_en', 'name_ar', 'icon']],
        ]);
});

it('returns active lists with their visible places through GET /api/place-lists', function (): void {
    $host = User::factory()->create(['phone' => '512345671']);
    $place = makeVisiblePlace($host);

    $list = PlaceList::query()->create([
        'name_en' => 'Top Picks',
        'name_ar' => 'الأفضل',
        'sort_order' => 0,
        'status' => 'active',
    ]);
    $list->places()->attach($place->id, ['sort_order' => 0]);

    $this->getJson('/api/place-lists')
        ->assertOk()
        ->assertJsonPath('data.0.name_en', 'Top Picks')
        ->assertJsonPath('data.0.places.0.id', $place->id)
        // Canonical PlaceResource shape — same as every other places list.
        ->assertJsonStructure([
            'data' => [['id', 'name_en', 'name_ar', 'icon', 'sort_order', 'places' => [[
                'id', 'title', 'price', 'per_day_prices', 'cover_photo_url',
                'type', 'city', 'city_area', 'likes_count', 'rating', 'is_liked',
            ]]]],
        ]);
});

it('drops lists with zero visible places from GET /api/place-lists', function (): void {
    $host = User::factory()->create(['phone' => '512345672']);
    $draft = makeVisiblePlace($host, ['review_status' => PlaceReviewStatus::Draft->value]);
    $list = PlaceList::query()->create([
        'name_en' => 'Empty', 'name_ar' => 'فارغة', 'sort_order' => 0, 'status' => 'active',
    ]);
    $list->places()->attach($draft->id, ['sort_order' => 0]);

    $this->getJson('/api/place-lists')->assertOk()->assertJsonCount(0, 'data');
});

it('ranks places by like count on GET /api/places/most-liked', function (): void {
    $host = User::factory()->create(['phone' => '512345673']);
    $popular = makeVisiblePlace($host, ['title' => 'Popular']);
    $quiet = makeVisiblePlace($host, ['title' => 'Quiet']);

    // 3 likes on Popular, 0 on Quiet.
    foreach (range(1, 3) as $i) {
        $u = User::factory()->create(['phone' => '51999000'.$i]);
        $u->likedPlaces()->attach($popular->id);
    }

    $this->getJson('/api/places/most-liked')
        ->assertOk()
        ->assertJsonPath('data.0.id', $popular->id)
        ->assertJsonPath('data.0.likes_count', 3)
        ->assertJsonPath('data.0.is_liked', false)
        ->assertJsonPath('data.1.id', $quiet->id)
        ->assertJsonPath('data.1.likes_count', 0);
});

it('excludes non-visible places from GET /api/places/most-liked', function (): void {
    $host = User::factory()->create(['phone' => '512345674']);
    makeVisiblePlace($host, ['review_status' => PlaceReviewStatus::PendingReview->value]);
    makeVisiblePlace($host, ['status' => PlaceStatus::Inactive->value]);

    $this->getJson('/api/places/most-liked')->assertOk()->assertJsonCount(0, 'data');
});

it('reflects is_liked when authed viewer calls GET /api/places/most-liked', function (): void {
    $host = User::factory()->create(['phone' => '512345675']);
    $place = makeVisiblePlace($host);

    $viewer = User::factory()->create(['phone' => '512345676']);
    $viewer->likedPlaces()->attach($place->id);
    $token = auth('api')->login($viewer);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/places/most-liked')
        ->assertOk()
        ->assertJsonPath('data.0.id', $place->id)
        ->assertJsonPath('data.0.is_liked', true)
        ->assertJsonPath('data.0.likes_count', 1);
});

it('includes review aggregates in PlaceResource', function (): void {
    $host = User::factory()->create(['phone' => '512345677']);
    $place = makeVisiblePlace($host);
    PlaceReview::query()->create(['place_id' => $place->id, 'rate' => 5, 'comment' => 'great']);
    PlaceReview::query()->create(['place_id' => $place->id, 'rate' => 3, 'comment' => 'ok']);

    $response = $this->getJson('/api/places/most-liked')->assertOk();
    expect($response->json('data.0.rating.count'))->toBe(2);
    expect((float) $response->json('data.0.rating.avg'))->toBe(4.0);
});

it('likes and unlikes a place through the auth endpoints', function (): void {
    $host = User::factory()->create(['phone' => '512345678']);
    $viewer = User::factory()->create(['phone' => '512345679']);
    $place = makeVisiblePlace($host);
    $token = auth('api')->login($viewer);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/places/'.$place->id.'/like')
        ->assertOk()
        ->assertJsonPath('data.is_liked', true);
    expect($viewer->likedPlaces()->where('places.id', $place->id)->exists())->toBeTrue();

    // Idempotent re-like — still liked, no duplicate row.
    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/places/'.$place->id.'/like')
        ->assertOk();
    expect($viewer->likedPlaces()->count())->toBe(1);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson('/api/places/'.$place->id.'/like')
        ->assertOk()
        ->assertJsonPath('data.is_liked', false);
    expect($viewer->likedPlaces()->count())->toBe(0);
});

it('rejects like/unlike when unauthenticated', function (): void {
    $host = User::factory()->create(['phone' => '512345670']);
    $place = makeVisiblePlace($host);

    $this->postJson('/api/places/'.$place->id.'/like')->assertStatus(401);
    $this->deleteJson('/api/places/'.$place->id.'/like')->assertStatus(401);
});
