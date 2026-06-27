<?php

declare(strict_types=1);

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;
use App\Services\Place\PlaceService;

beforeEach(function (): void {
    $this->seed();
    $this->host = User::factory()->create(['phone' => '512700001']);
});

function bilingualBase(array $overrides = []): array
{
    return array_merge([
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'price' => 500,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'location_url' => 'https://maps.google.com/?q=24.7,46.6',
        'extra_image_paths' => [
            'p/1.jpg', 'p/2.jpg', 'p/3.jpg', 'p/4.jpg', 'p/5.jpg',
        ],
    ], $overrides);
}

it('saves Arabic + English title and derives the canonical from Arabic', function (): void {
    $this->actingAs($this->host, 'api')
        ->post('/host-register', bilingualBase([
            'title_ar' => 'شاليه الواحة',
            'title_en' => 'Oasis Chalet',
            'description_ar' => 'وصف عربي',
            'description_en' => 'English description',
        ]))
        ->assertRedirect(route('user.places'));

    $place = Place::query()->latest('id')->first();
    expect($place->title_ar)->toBe('شاليه الواحة')
        ->and($place->title_en)->toBe('Oasis Chalet')
        ->and($place->title)->toBe('شاليه الواحة')              // canonical = *_ar
        ->and($place->description_en)->toBe('English description');
});

it('falls back to English for the canonical when only English is given', function (): void {
    $this->actingAs($this->host, 'api')
        ->post('/host-register', bilingualBase(['title_en' => 'English Only']))
        ->assertRedirect(route('user.places'));

    expect(Place::query()->latest('id')->first()->title)->toBe('English Only');
});

it('rejects a submit with neither title language', function (): void {
    $this->actingAs($this->host, 'api')
        ->from('/host-register')
        ->post('/host-register', bilingualBase())
        ->assertSessionHasErrors(['title_ar', 'title_en']);
});

it('returns Arabic or English for the localized accessor with fallback', function (): void {
    $place = Place::query()->create([
        'host_user_id' => $this->host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'شاليه الواحة',
        'title_ar' => 'شاليه الواحة',
        'title_en' => 'Oasis Chalet',
        'price' => 500, 'check_in_time' => '15:00', 'check_out_time' => '12:00', 'max_guests' => 4,
    ]);

    app()->setLocale('ar');
    expect($place->localized_title)->toBe('شاليه الواحة');

    app()->setLocale('en');
    expect($place->localized_title)->toBe('Oasis Chalet');

    // Missing English → falls back to Arabic.
    $place->title_en = null;
    expect($place->localized_title)->toBe('شاليه الواحة');
});

it('finds a place by its Arabic title in search', function (): void {
    Place::query()->create([
        'host_user_id' => $this->host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'شاليه الواحة', 'title_ar' => 'شاليه الواحة',
        'price' => 500, 'check_in_time' => '15:00', 'check_out_time' => '12:00', 'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ]);

    $cityId = CityArea::query()->first()->city_id;
    $results = app(PlaceService::class)->search(['city_id' => $cityId, 'q' => 'الواحة']);

    expect($results->total())->toBe(1);
});
