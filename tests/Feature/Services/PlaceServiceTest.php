<?php

declare(strict_types=1);

use App\Enums\PlaceReviewStatus;
use App\Enums\UserRole;
use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\City;
use App\Models\CityArea;
use App\Models\Country;
use App\Models\PlaceAttribute;
use App\Models\PlacePhoto;
use App\Models\PlaceType;
use App\Models\User;
use App\Services\Place\PlaceService;

/**
 * The wizard's draft auto-save passes a UUID string for the `$draftId`
 * argument. Before this regression test landed, `PlaceService::upsertPlace`
 * was typed `?int $draftId` while the public methods had been UUID-migrated
 * to `?string`, causing a TypeError the first time a host advanced past
 * step 1. We exercise the full draft → resume → promote flow here so any
 * future type drift fails in CI instead of in the browser.
 */

beforeEach(function (): void {
    $this->service = app(PlaceService::class);
    $this->host = User::factory()->create(['role' => UserRole::User->value]);

    $country = Country::query()->create([
        'country_code' => 'SA',
        'name_ar' => 'السعودية',
        'name_en' => 'Saudi Arabia',
    ]);
    $city = City::query()->create([
        'country_id' => $country->id,
        'name_ar' => 'الرياض',
        'name_en' => 'Riyadh',
    ]);
    $this->area = CityArea::query()->create([
        'city_id' => $city->id,
        'name_ar' => 'شمال',
        'name_en' => 'North',
    ]);
    $this->placeType = PlaceType::query()->create([
        'name_ar' => 'شاليهات',
        'name_en' => 'Chalets',
        'icon' => '🏖️',
    ]);
});

it('creates a fresh draft when no draftId is supplied', function (): void {
    $draft = $this->service->saveDraftForHost(
        $this->host,
        ['place_type_id' => $this->placeType->id],
    );

    expect($draft->id)->toBeString()
        ->and($draft->review_status)->toBe(PlaceReviewStatus::Draft)
        ->and($draft->host_user_id)->toBe($this->host->id);
});

it('reuses the same draft row when a uuid draftId is passed back', function (): void {
    $first = $this->service->saveDraftForHost(
        $this->host,
        ['place_type_id' => $this->placeType->id],
    );

    $second = $this->service->saveDraftForHost(
        $this->host,
        ['place_type_id' => $this->placeType->id, 'title' => 'Coast chalet'],
        $first->id,
    );

    expect($second->id)->toBe($first->id)
        ->and($second->title)->toBe('Coast chalet');
});

/**
 * Regression: bulk insert()/upsert() bypass Eloquent's `creating` event, so
 * any model using HasUuids must have its primary key minted manually in the
 * row array. The wizard's saveDraft flow hit a `NOT NULL constraint failed:
 * place_photos.id` because syncPhotos was calling PlacePhoto::insert without
 * an `id` column. Same hazard existed in syncAttributes via PlaceAttribute::upsert.
 */
it('persists attributes + photos through saveDraftForHost without NULL-id errors', function (): void {
    $group = AttributeGroup::query()->create(['name_ar' => 'مرافق', 'name_en' => 'Facilities']);
    $attribute = Attribute::query()->create([
        'group_id' => $group->id,
        'name_ar' => 'مسبح',
        'name_en' => 'Pool',
        'type' => 'boolean',
        'photo_rule' => 'optional',
    ]);

    $place = $this->service->saveDraftForHost(
        $this->host,
        ['place_type_id' => $this->placeType->id],
        null,
        // Attributes: would crash with NULL-id without the HasUuids stub fix.
        [[
            'attribute_id' => $attribute->id,
            'value' => '1',
            'description' => 'Heated',
        ]],
        // Photos: a per-attribute upload + an extra + a cover marker.
        [
            'attribute_paths' => [$attribute->id => ['places/uploads/a.jpg', 'places/uploads/b.jpg']],
            'extra_paths' => ['places/uploads/c.jpg'],
            'cover_key' => 'extra_images.0',
        ],
    );

    expect(PlaceAttribute::query()->where('place_id', $place->id)->count())->toBe(1)
        ->and(PlacePhoto::query()->where('place_id', $place->id)->count())->toBe(3)
        ->and(PlacePhoto::query()->where('place_id', $place->id)->where('is_cover', true)->count())->toBe(1);
});

it('promotes a draft to PendingReview on final submit (createForHost)', function (): void {
    $draft = $this->service->saveDraftForHost(
        $this->host,
        ['place_type_id' => $this->placeType->id],
    );

    $promoted = $this->service->createForHost(
        $this->host,
        [
            'place_type_id' => $this->placeType->id,
            'city_area_id' => $this->area->id,
            'title' => 'Coast chalet',
            'price' => 500,
        ],
        $draft->id,
    );

    expect($promoted->id)->toBe($draft->id)
        ->and($promoted->review_status)->toBe(PlaceReviewStatus::PendingReview)
        ->and($promoted->title)->toBe('Coast chalet');
});
