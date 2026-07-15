<?php

declare(strict_types=1);

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceAttribute;
use App\Models\PlacePhoto;
use App\Models\PlaceReview;
use App\Models\PlaceType;
use App\Models\User;

beforeEach(function (): void {
    $this->seed();
});

/**
 * Build a minimal Active+Approved place. Reused across cases to avoid
 * repeating the column dump.
 */
function detailPlace(array $attrs = []): Place
{
    $host = User::factory()->create(['phone' => '511'.fake()->unique()->numerify('######'), 'name' => 'Test Host']);

    return Place::query()->create(array_merge([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Detail test place',
        'description' => 'Long-form description.',
        'price' => 750,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'rules' => 'No smoking.',
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ], $attrs));
}

it('returns the full place shape via GET /api/places/{place}', function (): void {
    $place = detailPlace();

    $response = $this->getJson('/api/places/'.$place->id)
        ->assertOk()
        ->assertJsonPath('status', 200)
        ->assertJsonPath('data.id', $place->id)
        ->assertJsonPath('data.title', 'Detail test place')
        ->assertJsonPath('data.price', 750);

    // Spreads canonical PlaceResource fields at the top level...
    $response->assertJsonStructure(['data' => [
        'id', 'title', 'description', 'price', 'per_day_prices',
        'check_in_time', 'check_out_time', 'rules',
        'cover_photo_url', 'photos',
        'type', 'city', 'city_area',
        'likes_count', 'rating', 'is_liked',
        // ...and adds detail-only extras.
        'photo_groups', 'attributes', 'reviews_recent', 'host' => ['id', 'name', 'joined_at'],
    ]]);
});

it('returns photo_groups ordered by the host gallery arrangement', function (): void {
    $place = detailPlace();

    $group = AttributeGroup::query()->create(['name_en' => 'Rooms', 'name_ar' => 'غرف']);
    $bedroom = Attribute::query()->create([
        'group_id' => $group->id, 'name_en' => 'Bedroom', 'name_ar' => 'غرفة نوم',
        'icon' => '🛏️', 'type' => 'number', 'photo_rule' => 'optional',
    ]);
    $majlis = Attribute::query()->create([
        'group_id' => $group->id, 'name_en' => 'Majlis', 'name_ar' => 'مجلس',
        'icon' => '🛋️', 'type' => 'number', 'photo_rule' => 'optional',
    ]);
    PlaceAttribute::query()->create(['place_id' => $place->id, 'attribute_id' => $bedroom->id, 'value' => '1']);
    PlaceAttribute::query()->create(['place_id' => $place->id, 'attribute_id' => $majlis->id, 'value' => '1']);

    // Host arranged Majlis first (lowest sort_order), then Bedroom, general last.
    // featured_order is independent of the gallery section order.
    $rows = [
        ['attr' => $majlis->id, 'sort' => 0, 'featured' => 1],
        ['attr' => $majlis->id, 'sort' => 1, 'featured' => null],
        ['attr' => $bedroom->id, 'sort' => 2, 'featured' => 0],
        ['attr' => $bedroom->id, 'sort' => 3, 'featured' => null],
        ['attr' => null, 'sort' => 4, 'featured' => null],
    ];
    foreach ($rows as $i => $r) {
        PlacePhoto::query()->create([
            'place_id' => $place->id,
            'place_attribute_id' => $r['attr'],
            'path' => "places/uploads/p{$i}.jpg",
            'sort_order' => $r['sort'],
            'featured_order' => $r['featured'],
        ]);
    }

    $this->getJson('/api/places/'.$place->id)
        ->assertOk()
        // Sections ordered by min sort_order: Majlis (0) → Bedroom (2) → general.
        ->assertJsonPath('data.photo_groups.0.attribute.id', $majlis->id)
        ->assertJsonPath('data.photo_groups.0.attribute.name_en', 'Majlis')
        ->assertJsonPath('data.photo_groups.0.min_sort_order', 0)
        ->assertJsonPath('data.photo_groups.1.attribute.id', $bedroom->id)
        ->assertJsonPath('data.photo_groups.2.attribute', null)
        ->assertJsonPath('data.photo_groups.2.attribute_id', null)
        // Within a group, photos keep sort_order; featured_order rides along.
        ->assertJsonPath('data.photo_groups.0.photos.0.sort_order', 0)
        ->assertJsonPath('data.photo_groups.0.photos.0.featured_order', 1)
        ->assertJsonPath('data.photo_groups.0.photos.1.sort_order', 1);
});

it('hides legacy photos tied to a none-rule amenity from every photo payload', function (): void {
    $place = detailPlace();

    $group = AttributeGroup::query()->create(['name_en' => 'Amenities', 'name_ar' => 'مرافق']);
    // A none-rule amenity can still own photo rows from before its rule was
    // flipped to none — those must never reach the API.
    $wifi = Attribute::query()->create([
        'group_id' => $group->id, 'name_en' => 'WiFi', 'name_ar' => 'واي فاي',
        'icon' => '📶', 'type' => 'boolean', 'photo_rule' => 'none',
    ]);
    $pool = Attribute::query()->create([
        'group_id' => $group->id, 'name_en' => 'Pool', 'name_ar' => 'مسبح',
        'icon' => '🏊', 'type' => 'boolean', 'photo_rule' => 'optional',
    ]);
    PlaceAttribute::query()->create(['place_id' => $place->id, 'attribute_id' => $wifi->id, 'value' => '1']);
    PlaceAttribute::query()->create(['place_id' => $place->id, 'attribute_id' => $pool->id, 'value' => '1']);

    foreach ([
        ['attr' => $wifi->id, 'path' => 'places/uploads/wifi.jpg', 'sort' => 0, 'featured' => 0], // legacy, even featured as cover
        ['attr' => $pool->id, 'path' => 'places/uploads/pool.jpg', 'sort' => 1, 'featured' => 1],
        ['attr' => null, 'path' => 'places/uploads/general.jpg', 'sort' => 2, 'featured' => null],
    ] as $r) {
        PlacePhoto::query()->create([
            'place_id' => $place->id,
            'place_attribute_id' => $r['attr'],
            'path' => $r['path'],
            'sort_order' => $r['sort'],
            'featured_order' => $r['featured'],
        ]);
    }

    $response = $this->getJson('/api/places/'.$place->id)->assertOk();

    // Flat gallery + grouped gallery + showcase all skip the WiFi photo.
    $response->assertJsonCount(2, 'data.photos')
        ->assertJsonPath('data.photos.0.attribute_id', $pool->id)
        ->assertJsonPath('data.photos.1.attribute_id', null)
        ->assertJsonCount(2, 'data.photo_groups')
        ->assertJsonPath('data.photo_groups.0.attribute.id', $pool->id)
        ->assertJsonPath('data.photo_groups.1.attribute_id', null)
        ->assertJsonCount(1, 'data.featured_photos')
        ->assertJsonPath('data.featured_photos.0.attribute_id', $pool->id);

    // Even the cover skips it: WiFi held featured_order 0, but the cover
    // falls through to the next visible showcase photo.
    expect($response->json('data.cover_photo_url'))
        ->toContain('pool.jpg')
        ->not->toContain('wifi.jpg');
});

it('returns attributes grouped with their definition + group', function (): void {
    $place = detailPlace();

    $group = AttributeGroup::query()->create(['name_en' => 'Indoor', 'name_ar' => 'داخلي', 'is_standalone' => true]);
    $attribute = Attribute::query()->create([
        'group_id' => $group->id,
        'name_en' => 'WiFi',
        'name_ar' => 'واي فاي',
        'icon' => '📶',
        'type' => 'boolean',
        'photo_rule' => 'none',
    ]);
    PlaceAttribute::query()->create([
        'place_id' => $place->id,
        'attribute_id' => $attribute->id,
        'value' => '1',
        'description' => 'Fast fiber.',
    ]);

    $this->getJson('/api/places/'.$place->id)
        ->assertOk()
        ->assertJsonPath('data.attributes.0.value', '1')
        ->assertJsonPath('data.attributes.0.description', 'Fast fiber.')
        ->assertJsonPath('data.attributes.0.attribute.name_en', 'WiFi')
        ->assertJsonPath('data.attributes.0.attribute.icon', '📶')
        ->assertJsonPath('data.attributes.0.attribute.group.name_en', 'Indoor')
        // The app splits standalone groups into their own section from this flag.
        ->assertJsonPath('data.attributes.0.attribute.group.is_standalone', true);
});

it('orders attributes by the admin sort_order and exposes is_highlighted', function (): void {
    $place = detailPlace();
    $group = AttributeGroup::query()->create(['name_en' => 'Indoor', 'name_ar' => 'داخلي']);

    // Created Pool-first, but sort_order should make Pool come second.
    $pool = Attribute::query()->create([
        'group_id' => $group->id, 'name_en' => 'Pool', 'name_ar' => 'مسبح',
        'icon' => '🏊', 'type' => 'boolean', 'photo_rule' => 'none',
        'is_highlighted' => true, 'sort_order' => 2,
    ]);
    $wifi = Attribute::query()->create([
        'group_id' => $group->id, 'name_en' => 'WiFi', 'name_ar' => 'واي فاي',
        'icon' => '📶', 'type' => 'boolean', 'photo_rule' => 'none',
        'is_highlighted' => false, 'sort_order' => 1,
    ]);
    foreach ([$pool, $wifi] as $a) {
        PlaceAttribute::query()->create(['place_id' => $place->id, 'attribute_id' => $a->id, 'value' => '1']);
    }

    $this->getJson('/api/places/'.$place->id)
        ->assertOk()
        // sort_order 1 (WiFi) before 2 (Pool), regardless of insert order.
        ->assertJsonPath('data.attributes.0.attribute.name_en', 'WiFi')
        ->assertJsonPath('data.attributes.0.attribute.is_highlighted', false)
        ->assertJsonPath('data.attributes.0.attribute.sort_order', 1)
        ->assertJsonPath('data.attributes.1.attribute.name_en', 'Pool')
        ->assertJsonPath('data.attributes.1.attribute.is_highlighted', true)
        ->assertJsonPath('data.attributes.1.attribute.sort_order', 2);
});

it('returns the 10 most recent reviews on the detail screen', function (): void {
    $place = detailPlace();

    foreach (range(1, 12) as $i) {
        PlaceReview::query()->create([
            'place_id' => $place->id,
            'rate' => 5,
            'comment' => "review #$i",
            'status' => 'published',
        ]);
    }

    $this->getJson('/api/places/'.$place->id)
        ->assertOk()
        ->assertJsonCount(10, 'data.reviews_recent')
        ->assertJsonPath('data.rating.count', 12)
        ->assertJsonStructure(['data' => ['reviews_recent' => [['id', 'rate', 'comment', 'created_at']]]]);
});

it('exposes only public host info — no phone or email leaks', function (): void {
    $place = detailPlace();

    $body = $this->getJson('/api/places/'.$place->id)->assertOk()->json('data.host');
    expect($body)->toHaveKeys(['id', 'name', 'joined_at']);
    expect(array_keys($body))->toBe(['id', 'name', 'joined_at']);  // exact set, nothing extra
});

it('reflects is_liked when an authed viewer fetches the place', function (): void {
    $place = detailPlace();
    $viewer = User::factory()->create(['phone' => '512345099']);
    $viewer->likedPlaces()->attach($place->id);
    $token = auth('api')->login($viewer);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/places/'.$place->id)
        ->assertOk()
        ->assertJsonPath('data.is_liked', true)
        ->assertJsonPath('data.likes_count', 1);
});

it('returns 404 for a draft place', function (): void {
    $draft = detailPlace(['review_status' => PlaceReviewStatus::Draft->value]);

    $this->getJson('/api/places/'.$draft->id)
        ->assertStatus(404)
        ->assertJsonPath('message', 'Place not found.');
});

it('returns 404 for an inactive (approved but disabled) place', function (): void {
    $inactive = detailPlace(['status' => PlaceStatus::Inactive->value]);

    $this->getJson('/api/places/'.$inactive->id)->assertStatus(404);
});

it('returns 404 for a pending or rejected place', function (): void {
    foreach ([PlaceReviewStatus::PendingReview->value, PlaceReviewStatus::Rejected->value] as $status) {
        $place = detailPlace(['review_status' => $status]);
        $this->getJson('/api/places/'.$place->id)->assertStatus(404);
    }
});
