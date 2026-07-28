<?php

declare(strict_types=1);

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Enums\UserRole;
use App\Models\City;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;

beforeEach(function (): void {
    $this->seed();
    $this->admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '598100001']);
    $this->host = User::factory()->create(['phone' => '516100001']);
    $this->actingAs($this->admin, 'api');
});

function indexPlace(User $host, CityArea $area, array $attrs = []): Place
{
    return Place::query()->create(array_merge([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => $area->id,
        'title' => 'Filter test '.fake()->unique()->numerify('####'),
        'description' => 'x',
        'price' => 500,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ], $attrs));
}

it('filters the admin places list by city', function (): void {
    // Two areas in two DIFFERENT cities.
    $areaA = CityArea::query()->first();
    $cityB = City::query()->whereKeyNot($areaA->city_id)->first();
    $areaB = CityArea::query()->where('city_id', $cityB->id)->first()
        ?? CityArea::query()->create(['city_id' => $cityB->id, 'name_ar' => 'حي', 'name_en' => 'Area B']);

    $inCityA = indexPlace($this->host, $areaA, ['title' => 'Chalet In City A']);
    $inCityB = indexPlace($this->host, $areaB, ['title' => 'Chalet In City B']);

    // Filtered → only the matching city's place.
    $this->get('/admin/places?city='.$areaA->city_id)
        ->assertOk()
        ->assertSee('Chalet In City A')
        ->assertDontSee('Chalet In City B');

    // No filter → both.
    $this->get('/admin/places')
        ->assertOk()
        ->assertSee('Chalet In City A')
        ->assertSee('Chalet In City B');
});

it('filters by area within a city and scopes the stat counts to the filters', function (): void {
    // Two areas in the SAME city.
    $areaA = CityArea::query()->first();
    $areaB = CityArea::query()->create(['city_id' => $areaA->city_id, 'name_ar' => 'حي ب', 'name_en' => 'Sibling Area']);

    indexPlace($this->host, $areaA, ['title' => 'Chalet In Area A']);
    indexPlace($this->host, $areaB, ['title' => 'Chalet In Area B']);

    $this->get('/admin/places?city='.$areaA->city_id.'&area='.$areaA->id)
        ->assertOk()
        ->assertSee('Chalet In Area A')
        ->assertDontSee('Chalet In Area B')
        // Stats on top reflect the active filters, not the whole table.
        ->assertViewHas('counts', fn (array $counts): bool => $counts['total'] === 1 && $counts['active'] === 1)
        // Dependent dropdown lists the chosen city's areas.
        ->assertViewHas('areas', fn ($areas): bool => $areas->pluck('id')->contains($areaB->id));

    // City filter alone still counts both.
    $this->get('/admin/places?city='.$areaA->city_id)
        ->assertOk()
        ->assertViewHas('counts', fn (array $counts): bool => $counts['total'] === 2);
});

it('ignores the area param when no city is selected', function (): void {
    $area = CityArea::query()->first();
    indexPlace($this->host, $area, ['title' => 'Chalet Without City Filter']);

    $this->get('/admin/places?area='.$area->id)
        ->assertOk()
        ->assertSee('Chalet Without City Filter')
        ->assertViewHas('areaId', null);
});

it('walks the review queue forward on skip instead of bouncing back (1→2→3→1)', function (): void {
    $area = CityArea::query()->first();

    $first = indexPlace($this->host, $area, ['review_status' => PlaceReviewStatus::PendingReview->value, 'status' => PlaceStatus::Inactive->value]);
    $this->travel(2)->seconds();
    $second = indexPlace($this->host, $area, ['review_status' => PlaceReviewStatus::PendingReview->value, 'status' => PlaceStatus::Inactive->value]);
    $this->travel(2)->seconds();
    $third = indexPlace($this->host, $area, ['review_status' => PlaceReviewStatus::PendingReview->value, 'status' => PlaceStatus::Inactive->value]);
    $this->travelBack();

    // Skip 1 → 2, skip 2 → 3 (the old logic bounced back to 1 here),
    // skip 3 → wraps to 1.
    $this->post("/admin/places/{$first->id}/review/skip")
        ->assertRedirect(route('admin.places.review', $second));
    $this->post("/admin/places/{$second->id}/review/skip")
        ->assertRedirect(route('admin.places.review', $third));
    $this->post("/admin/places/{$third->id}/review/skip")
        ->assertRedirect(route('admin.places.review', $first));
});

it('approving mid-queue continues to the oldest remaining pending place', function (): void {
    $area = CityArea::query()->first();

    $first = indexPlace($this->host, $area, ['review_status' => PlaceReviewStatus::PendingReview->value, 'status' => PlaceStatus::Inactive->value]);
    $this->travel(2)->seconds();
    $second = indexPlace($this->host, $area, ['review_status' => PlaceReviewStatus::PendingReview->value, 'status' => PlaceStatus::Inactive->value]);
    $this->travelBack();

    // Approving 2 removes it from the queue; the redirect goes to 1.
    $this->post("/admin/places/{$second->id}/review/approve")
        ->assertRedirect(route('admin.places.review', $first));

    expect($second->refresh()->review_status)->toBe(PlaceReviewStatus::Approved);
});
