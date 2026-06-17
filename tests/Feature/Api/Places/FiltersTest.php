<?php

declare(strict_types=1);

use App\Enums\GeoStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\City;
use App\Models\CityArea;
use App\Models\Country;
use App\Models\Place;
use App\Models\PlaceAttribute;
use App\Models\PlaceType;
use App\Models\User;

beforeEach(function (): void {
    $this->seed();
});

function facetCity(): City
{
    return City::query()->create([
        'country_id' => Country::query()->first()->id,
        'name_ar' => 'مدينة',
        'name_en' => 'City '.fake()->unique()->numerify('###'),
        'avatar' => '🏙️',
        'status' => GeoStatus::Active->value,
    ]);
}

function facetArea(City $city, string $name): CityArea
{
    return CityArea::query()->create(['city_id' => $city->id, 'name_ar' => $name, 'name_en' => $name]);
}

function facetPlace(CityArea $area, PlaceType $type, array $attrs = []): Place
{
    $host = User::factory()->create(['phone' => '55'.fake()->unique()->numerify('#######')]);

    return Place::query()->create(array_merge([
        'host_user_id' => $host->id,
        'place_type_id' => $type->id,
        'city_area_id' => $area->id,
        'title' => 'P '.fake()->unique()->numerify('###'),
        'description' => 'd',
        'price' => 1000,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ], $attrs));
}

function attachAmenity(Place $place, Attribute $a): void
{
    PlaceAttribute::query()->create(['place_id' => $place->id, 'attribute_id' => $a->id, 'value' => '1']);
}

it('requires a city_id', function (): void {
    $this->getJson('/api/places/filters')->assertStatus(422);
});

it('returns the available facets for a city', function (): void {
    $city = facetCity();
    $areaA = facetArea($city, 'Area A');
    $areaB = facetArea($city, 'Area B');
    facetArea($city, 'Area C'); // empty → must not appear

    $typeA = PlaceType::query()->first();
    $typeB = PlaceType::query()->create(['name_en' => 'Rest House', 'name_ar' => 'استراحة', 'icon' => '🏡', 'status' => GeoStatus::Active->value]);

    $group = AttributeGroup::query()->create(['name_en' => 'Facilities', 'name_ar' => 'مرافق']);
    $wifi = Attribute::query()->create(['group_id' => $group->id, 'name_en' => 'WiFi', 'name_ar' => 'واي فاي', 'icon' => '📶', 'type' => 'boolean', 'photo_rule' => 'none']);
    $pool = Attribute::query()->create(['group_id' => $group->id, 'name_en' => 'Pool', 'name_ar' => 'مسبح', 'icon' => '🏊', 'type' => 'boolean', 'photo_rule' => 'none']);

    $p1 = facetPlace($areaA, $typeA, ['price' => 1000, 'max_guests' => 4]);
    attachAmenity($p1, $wifi);
    attachAmenity($p1, $pool);

    $p2 = facetPlace($areaB, $typeB, ['price' => 3000, 'max_guests' => 8]);
    attachAmenity($p2, $wifi);

    // Noise that must be excluded from facets:
    facetPlace($areaA, $typeA, ['review_status' => PlaceReviewStatus::Draft->value, 'price' => 99]); // not visible
    $otherCity = facetCity();
    facetPlace(facetArea($otherCity, 'Other'), $typeA, ['price' => 50, 'max_guests' => 50]);          // other city

    $res = $this->getJson("/api/places/filters?city_id={$city->id}")
        ->assertOk()
        ->assertJsonPath('data.city_id', $city->id)
        ->assertJsonPath('data.currency', 'SAR')
        ->assertJsonPath('data.price.min', 1000)
        ->assertJsonPath('data.price.max', 3000)
        ->assertJsonPath('data.guests.min', 4)
        ->assertJsonPath('data.guests.max', 8);

    // Areas: only A and B (C excluded).
    $areaNames = collect($res->json('data.areas'))->pluck('name_en')->sort()->values()->all();
    expect($areaNames)->toBe(['Area A', 'Area B']);

    // Place types in use: both.
    expect(collect($res->json('data.place_types'))->count())->toBe(2);

    // Amenities grouped; counts reflect usage.
    $amenities = $res->json('data.amenities');
    expect($amenities)->toHaveCount(1);
    expect($amenities[0]['group']['name_en'])->toBe('Facilities');
    $byName = collect($amenities[0]['items'])->keyBy('name_en');
    expect($byName['WiFi']['places_count'])->toBe(2);
    expect($byName['Pool']['places_count'])->toBe(1);
});

it('returns empty facets and zero ranges for a city with no places', function (): void {
    $city = facetCity();
    facetArea($city, 'Empty Area');

    $this->getJson("/api/places/filters?city_id={$city->id}")
        ->assertOk()
        ->assertJsonPath('data.price.min', 0)
        ->assertJsonPath('data.price.max', 0)
        ->assertJsonPath('data.areas', [])
        ->assertJsonPath('data.place_types', [])
        ->assertJsonPath('data.amenities', []);
});
