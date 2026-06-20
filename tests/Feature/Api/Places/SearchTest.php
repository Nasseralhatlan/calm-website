<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\GeoStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\Booking;
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

function searchCity(): City
{
    return City::query()->create([
        'country_id' => Country::query()->first()->id,
        'name_ar' => 'مدينة',
        'name_en' => 'City '.fake()->unique()->numerify('###'),
        'avatar' => '🏙️',
        'status' => GeoStatus::Active->value,
    ]);
}

function searchArea(City $city): CityArea
{
    return CityArea::query()->create([
        'city_id' => $city->id,
        'name_ar' => 'حي',
        'name_en' => 'Area '.fake()->unique()->numerify('###'),
    ]);
}

function searchPlace(CityArea $area, array $attrs = []): Place
{
    $host = User::factory()->create(['phone' => '52'.fake()->unique()->numerify('#######')]);

    return Place::query()->create(array_merge([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => $area->id,
        'title' => 'Place '.fake()->unique()->numerify('####'),
        'description' => 'A nice place.',
        'price' => 1000,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ], $attrs));
}

function searchAmenity(string $name): Attribute
{
    $group = AttributeGroup::query()->create(['name_en' => 'G'.fake()->unique()->numerify('##'), 'name_ar' => 'مجموعة']);

    return Attribute::query()->create([
        'group_id' => $group->id,
        'name_en' => $name,
        'name_ar' => $name,
        'icon' => '⭐',
        'type' => 'boolean',
        'photo_rule' => 'none',
    ]);
}

function searchConfirmedBooking(Place $place, string $start, string $end): Booking
{
    return Booking::query()->create([
        'place_id' => $place->id,
        'guest_user_id' => User::factory()->create(['phone' => '53'.fake()->unique()->numerify('#######')])->id,
        'host_user_id' => $place->host_user_id,
        'booking_status' => BookingStatus::Confirmed->value,
        'start_date' => $start,
        'end_date' => $end,
        'guests' => 2,
        'booking_price' => 100000,
        'quantity' => 2,
        'booking_amount' => 200000,
        'commission_rate' => 10,
        'commission_amount' => 20000,
        'vat_rate' => 15,
        'vat_amount' => 30000,
        'total' => 230000,
        'payout_status' => 'not_paid',
    ]);
}

it('requires a city_id', function (): void {
    $this->getJson('/api/places/search')->assertStatus(422);
});

it('returns only visible places in the requested city', function (): void {
    $city = searchCity();
    $area = searchArea($city);
    $wanted = searchPlace($area, ['title' => 'Wanted Place']);
    searchPlace($area, ['review_status' => PlaceReviewStatus::Draft->value]); // not visible
    searchPlace($area, ['status' => PlaceStatus::Inactive->value]);           // not visible

    // Different city.
    $other = searchCity();
    searchPlace(searchArea($other));

    $this->getJson("/api/places/search?city_id={$city->id}")
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.items.0.id', $wanted->id)
        ->assertJsonStructure(['data' => ['items' => [['id', 'title', 'price', 'is_liked', 'rating']], 'pagination' => ['page', 'per_page', 'total', 'has_more']]]);
});

it('filters by place type', function (): void {
    $city = searchCity();
    $area = searchArea($city);
    $typeB = PlaceType::query()->create(['name_en' => 'Rest House', 'name_ar' => 'استراحة', 'icon' => '🏡', 'status' => GeoStatus::Active->value]);

    searchPlace($area); // default seeded type
    $match = searchPlace($area, ['place_type_id' => $typeB->id]);

    $this->getJson("/api/places/search?city_id={$city->id}&place_type_ids[]={$typeB->id}")
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.items.0.id', $match->id);
});

it('filters by price range', function (): void {
    $city = searchCity();
    $area = searchArea($city);
    $cheap = searchPlace($area, ['price' => 1000]);
    $pricey = searchPlace($area, ['price' => 3000]);

    $this->getJson("/api/places/search?city_id={$city->id}&price_max=2000")
        ->assertOk()->assertJsonPath('data.pagination.total', 1)->assertJsonPath('data.items.0.id', $cheap->id);

    $this->getJson("/api/places/search?city_id={$city->id}&price_min=2000")
        ->assertOk()->assertJsonPath('data.pagination.total', 1)->assertJsonPath('data.items.0.id', $pricey->id);
});

it('filters by minimum guest capacity', function (): void {
    $city = searchCity();
    $area = searchArea($city);
    searchPlace($area, ['max_guests' => 2]);
    $big = searchPlace($area, ['max_guests' => 10]);

    $this->getJson("/api/places/search?city_id={$city->id}&guests=6")
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.items.0.id', $big->id);
});

it('matches ALL selected amenities', function (): void {
    $city = searchCity();
    $area = searchArea($city);
    $wifi = searchAmenity('WiFi');
    $pool = searchAmenity('Pool');

    $wifiOnly = searchPlace($area);
    PlaceAttribute::query()->create(['place_id' => $wifiOnly->id, 'attribute_id' => $wifi->id, 'value' => '1']);

    $both = searchPlace($area);
    PlaceAttribute::query()->create(['place_id' => $both->id, 'attribute_id' => $wifi->id, 'value' => '1']);
    PlaceAttribute::query()->create(['place_id' => $both->id, 'attribute_id' => $pool->id, 'value' => '1']);

    // Needs BOTH → only the place that has both.
    $this->getJson("/api/places/search?city_id={$city->id}&amenities[]={$wifi->id}&amenities[]={$pool->id}")
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.items.0.id', $both->id);

    // Needs WiFi → both qualify.
    $this->getJson("/api/places/search?city_id={$city->id}&amenities[]={$wifi->id}")
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 2);
});

it('excludes places that are booked or blocked for the requested dates', function (): void {
    $city = searchCity();
    $area = searchArea($city);
    $checkIn = now()->addDays(5)->toDateString();
    $checkOut = now()->addDays(7)->toDateString();

    $free = searchPlace($area, ['title' => 'Free']);
    $booked = searchPlace($area, ['title' => 'Booked']);
    searchConfirmedBooking($booked, now()->addDays(4)->toDateString(), now()->addDays(6)->toDateString()); // overlaps
    $blocked = searchPlace($area, ['title' => 'Blocked']);
    $blocked->blockings()->create(['start_date' => now()->addDays(6)->toDateString(), 'end_date' => now()->addDays(8)->toDateString()]); // overlaps

    $this->getJson("/api/places/search?city_id={$city->id}&check_in={$checkIn}&check_out={$checkOut}")
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.items.0.id', $free->id);

    // Without dates, all three are returned.
    $this->getJson("/api/places/search?city_id={$city->id}")
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 3);
});

it('sorts by price ascending and descending', function (): void {
    $city = searchCity();
    $area = searchArea($city);
    searchPlace($area, ['price' => 3000]);
    searchPlace($area, ['price' => 1000]);
    searchPlace($area, ['price' => 2000]);

    $asc = $this->getJson("/api/places/search?city_id={$city->id}&sort=price_asc")->assertOk()->json('data.items');
    expect(array_column($asc, 'price'))->toBe([1000, 2000, 3000]);

    $desc = $this->getJson("/api/places/search?city_id={$city->id}&sort=price_desc")->assertOk()->json('data.items');
    expect(array_column($desc, 'price'))->toBe([3000, 2000, 1000]);
});

it('reflects is_liked for an authenticated viewer', function (): void {
    $city = searchCity();
    $area = searchArea($city);
    $place = searchPlace($area);
    $viewer = User::factory()->create(['phone' => '512999111']);
    $viewer->likedPlaces()->attach($place->id);

    $this->actingAs($viewer, 'api')
        ->getJson("/api/places/search?city_id={$city->id}")
        ->assertOk()
        ->assertJsonPath('data.items.0.is_liked', true);
});

it('paginates results', function (): void {
    config(['pagination.per_page' => 2]); // page size is server-controlled now, not ?per_page=
    $city = searchCity();
    $area = searchArea($city);
    searchPlace($area);
    searchPlace($area);
    searchPlace($area);

    $this->getJson("/api/places/search?city_id={$city->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.pagination.per_page', 2)
        ->assertJsonPath('data.pagination.total', 3)
        ->assertJsonPath('data.pagination.last_page', 2)
        ->assertJsonPath('data.pagination.has_more', true);
});
