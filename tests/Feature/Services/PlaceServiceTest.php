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
        // Photos: a per-attribute upload + an extra + a featured/cover marker.
        [
            'attribute_paths' => [$attribute->id => ['places/uploads/a.jpg', 'places/uploads/b.jpg']],
            'extra_paths' => ['places/uploads/c.jpg'],
            'featured' => ['extra_images.0'],
        ],
    );

    expect(PlaceAttribute::query()->where('place_id', $place->id)->count())->toBe(1)
        ->and(PlacePhoto::query()->where('place_id', $place->id)->count())->toBe(3)
        // The single featured photo (the extra) is the cover at featured_order 0.
        ->and(PlacePhoto::query()->where('place_id', $place->id)->where('featured_order', 0)->count())->toBe(1)
        ->and(PlacePhoto::query()->where('place_id', $place->id)->where('path', 'places/uploads/c.jpg')->value('featured_order'))->toBe(0);
});

/**
 * The wizard POSTs attribute groups in the host's chosen section order and
 * photos within each group in their arranged order. syncPhotos must assign
 * sort_order group-then-within (extras last) and resolve featured_order from
 * the featured markers — `featured_order === 0` is the cover.
 */
it('orders photos by section then within, and maps featured_order from markers', function (): void {
    $group = AttributeGroup::query()->create(['name_ar' => 'مرافق', 'name_en' => 'Facilities']);
    $pool = Attribute::query()->create([
        'group_id' => $group->id, 'name_ar' => 'مسبح', 'name_en' => 'Pool',
        'type' => 'boolean', 'photo_rule' => 'optional',
    ]);
    $bath = Attribute::query()->create([
        'group_id' => $group->id, 'name_ar' => 'حمام', 'name_en' => 'Bathroom',
        'type' => 'boolean', 'photo_rule' => 'optional',
    ]);

    $place = $this->service->saveDraftForHost(
        $this->host,
        ['place_type_id' => $this->placeType->id],
        null,
        [
            ['attribute_id' => $pool->id, 'value' => '1', 'description' => null],
            ['attribute_id' => $bath->id, 'value' => '1', 'description' => null],
        ],
        [
            // Host arranged the pool section first, bathroom second, extras last.
            'attribute_paths' => [
                $pool->id => ['places/uploads/pool-1.jpg', 'places/uploads/pool-2.jpg'],
                $bath->id => ['places/uploads/bath-1.jpg'],
            ],
            'extra_paths' => ['places/uploads/extra-1.jpg'],
            // Showcase: bathroom photo is the cover, then the second pool photo.
            'featured' => ['attribute_images.'.$bath->id.'.0', 'attribute_images.'.$pool->id.'.1'],
        ],
    );

    $ordered = PlacePhoto::query()->where('place_id', $place->id)->orderBy('sort_order')->pluck('path')->all();
    expect($ordered)->toBe([
        'places/uploads/pool-1.jpg',
        'places/uploads/pool-2.jpg',
        'places/uploads/bath-1.jpg',
        'places/uploads/extra-1.jpg',
    ]);

    // Cover (featured_order 0) is the bathroom photo; rank 1 is the 2nd pool photo.
    expect(PlacePhoto::query()->where('place_id', $place->id)->where('path', 'places/uploads/bath-1.jpg')->value('featured_order'))->toBe(0)
        ->and(PlacePhoto::query()->where('place_id', $place->id)->where('path', 'places/uploads/pool-2.jpg')->value('featured_order'))->toBe(1)
        // The unfeatured photos carry a null featured_order.
        ->and(PlacePhoto::query()->where('place_id', $place->id)->where('path', 'places/uploads/pool-1.jpg')->value('featured_order'))->toBeNull()
        ->and(PlacePhoto::query()->where('place_id', $place->id)->where('path', 'places/uploads/extra-1.jpg')->value('featured_order'))->toBeNull();

    // Relations: coverPhoto = first featured; featuredPhotos = ordered showcase.
    $place->refresh();
    expect($place->coverPhoto?->path)->toBe('places/uploads/bath-1.jpg')
        ->and($place->featuredPhotos->pluck('path')->all())->toBe([
            'places/uploads/bath-1.jpg',
            'places/uploads/pool-2.jpg',
        ]);
});

it('coerces blank (null) per-day prices to 0 so the non-nullable columns persist', function (): void {
    // The wizard sends a value only for days the host customized; untouched
    // days arrive as null (empty inputs nulled by middleware). They must land
    // as 0 ("use base price"), not blow up on the NOT NULL constraint.
    $place = $this->service->createForHost(
        $this->host,
        [
            'place_type_id' => $this->placeType->id,
            'city_area_id' => $this->area->id,
            'title_ar' => 'شاليه',
            'price' => 500,
            'price_thursday' => 700,
            'price_friday' => 800,
            'price_sunday' => null,
            'price_monday' => null,
            'price_tuesday' => null,
            'price_wednesday' => null,
            'price_saturday' => null,
        ],
        null,
    );

    expect($place->price_thursday)->toBe(700)
        ->and($place->price_friday)->toBe(800)
        ->and($place->price_sunday)->toBe(0)
        ->and($place->price_monday)->toBe(0)
        ->and($place->price_saturday)->toBe(0);
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
