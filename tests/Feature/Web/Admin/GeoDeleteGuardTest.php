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
    $this->admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '598100002']);
    $this->host = User::factory()->create(['phone' => '516100002']);
    $this->actingAs($this->admin, 'api');
});

function geoGuardPlace(User $host, CityArea $area): Place
{
    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => $area->id,
        'title' => 'Geo guard '.fake()->unique()->numerify('####'),
        'description' => 'x',
        'price' => 500,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ]);
}

it('refuses to delete an area that still has places, with a readable error', function (): void {
    $area = CityArea::query()->first();
    geoGuardPlace($this->host, $area);

    $this->from(route('admin.city-areas.index'))
        ->delete(route('admin.city-areas.destroy', $area))
        ->assertRedirect(route('admin.city-areas.index'))
        ->assertSessionHasErrors('city_area');

    expect(CityArea::query()->whereKey($area->id)->exists())->toBeTrue();
});

it('deletes an area with no places normally', function (): void {
    $area = CityArea::query()->create([
        'city_id' => City::query()->first()->id,
        'name_ar' => 'حي فارغ',
        'name_en' => 'Empty Area',
    ]);

    $this->delete(route('admin.city-areas.destroy', $area))
        ->assertRedirect(route('admin.city-areas.index'))
        ->assertSessionDoesntHaveErrors();

    expect(CityArea::query()->whereKey($area->id)->exists())->toBeFalse();
});

it('refuses to delete a city whose areas still have places', function (): void {
    $area = CityArea::query()->first();
    geoGuardPlace($this->host, $area);

    $this->from(route('admin.cities.index'))
        ->delete(route('admin.cities.destroy', $area->city_id))
        ->assertRedirect(route('admin.cities.index'))
        ->assertSessionHasErrors('city');

    expect(City::query()->whereKey($area->city_id)->exists())->toBeTrue();
});
