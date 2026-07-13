<?php

declare(strict_types=1);

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\Attribute;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceAttribute;
use App\Models\PlacePhoto;
use App\Models\PlaceType;
use App\Models\User;

beforeEach(function (): void {
    $this->seed();
    $this->host = User::factory()->create(['phone' => '514200001']);
    // Photo urls resolve through the s3 disk — point it at a fake CDN.
    config()->set('filesystems.disks.s3.url', 'https://cdn.calm.test');
});

function apiEditablePlace(User $host, array $overrides = []): Place
{
    return Place::query()->create(array_merge([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'شاليه',
        'title_ar' => 'شاليه',
        'title_en' => 'Chalet',
        'description' => 'وصف',
        'description_ar' => 'وصف',
        'price' => 900,
        'price_friday' => 1200,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'checkout_next_day' => true,
        'max_guests' => 8,
        'location_url' => 'https://maps.google.com/?q=24.7,46.6',
        'last_step' => 5,
        'status' => PlaceStatus::Inactive->value,
        'review_status' => PlaceReviewStatus::Draft->value,
    ], $overrides));
}

it('returns the full editable shape for the owner, drafts included', function (): void {
    $place = apiEditablePlace($this->host);
    $attribute = Attribute::query()->first();

    PlaceAttribute::query()->create([
        'place_id' => $place->id,
        'attribute_id' => $attribute->id,
        'value' => '1',
        'description' => 'Heated',
    ]);
    PlacePhoto::query()->create([
        'place_id' => $place->id,
        'place_attribute_id' => $attribute->id,
        'path' => 'a/1.jpg',
        'sort_order' => 0,
        'featured_order' => 0,
    ]);
    PlacePhoto::query()->create([
        'place_id' => $place->id,
        'place_attribute_id' => null,
        'path' => 'p/1.jpg',
        'sort_order' => 1,
        'featured_order' => null,
    ]);

    $this->actingAs($this->host, 'api')
        ->getJson("/api/host/places/{$place->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $place->id)
        ->assertJsonPath('data.review_status', 'draft')
        ->assertJsonPath('data.title_ar', 'شاليه')
        ->assertJsonPath('data.title_en', 'Chalet')
        ->assertJsonPath('data.price', 900)
        ->assertJsonPath('data.price_friday', 1200)
        ->assertJsonPath('data.checkout_next_day', true)
        ->assertJsonPath('data.last_step', 5)
        // city_id resolved through the area for the 2-stage picker.
        ->assertJsonPath('data.city_id', CityArea::query()->first()->city_id)
        ->assertJsonPath('data.city_area_id', $place->city_area_id)
        // Amenity values in wizard shape.
        ->assertJsonPath('data.attributes.0.attribute_id', $attribute->id)
        ->assertJsonPath('data.attributes.0.value', '1')
        ->assertJsonPath('data.attributes.0.description', 'Heated')
        // Flat photo list — the app regroups client-side like the web JS.
        ->assertJsonCount(2, 'data.photos')
        ->assertJsonPath('data.photos.0.place_attribute_id', $attribute->id)
        ->assertJsonPath('data.photos.0.path', 'a/1.jpg')
        ->assertJsonPath('data.photos.0.featured_order', 0)
        ->assertJsonPath('data.photos.1.place_attribute_id', null)
        ->assertJsonPath('data.photos.1.featured_order', null)
        ->assertJsonStructure(['data' => ['photos' => [['place_attribute_id', 'path', 'url', 'featured_order', 'sort_order']]]]);
});

it('omits legacy none-rule amenity photos from the editable payload', function (): void {
    $place = apiEditablePlace($this->host);
    // A photo owned by a none-rule amenity (rule was flipped after upload):
    // the wizard has no box to show it in, so the API hides it.
    $noPhoto = Attribute::query()->create([
        'group_id' => Attribute::query()->first()->group_id,
        'name_en' => 'Quiet area', 'name_ar' => 'منطقة هادئة',
        'icon' => '🤫', 'type' => 'boolean', 'photo_rule' => 'none',
    ]);

    PlacePhoto::query()->create([
        'place_id' => $place->id, 'place_attribute_id' => $noPhoto->id,
        'path' => 'a/hidden.jpg', 'sort_order' => 0, 'featured_order' => null,
    ]);
    PlacePhoto::query()->create([
        'place_id' => $place->id, 'place_attribute_id' => null,
        'path' => 'p/visible.jpg', 'sort_order' => 1, 'featured_order' => null,
    ]);

    $this->actingAs($this->host, 'api')
        ->getJson("/api/host/places/{$place->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.photos')
        ->assertJsonPath('data.photos.0.path', 'p/visible.jpg');
});

it('is owner-only on every verb', function (): void {
    $place = apiEditablePlace($this->host);
    $other = User::factory()->create(['phone' => '514200002']);

    $this->actingAs($other, 'api')->getJson("/api/host/places/{$place->id}")->assertForbidden();
    // A well-formed body so validation passes and the ownership guard answers.
    $this->actingAs($other, 'api')->putJson("/api/host/places/{$place->id}", [
        'title_ar' => 'اختراق',
        'place_type_id' => $place->place_type_id,
        'city_area_id' => $place->city_area_id,
        'price' => 100,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 2,
        'location_url' => 'https://maps.google.com/?q=1,1',
    ])->assertForbidden();
    $this->actingAs($other, 'api')->deleteJson("/api/host/places/{$place->id}")->assertForbidden();
});

it('requires authentication', function (): void {
    $place = apiEditablePlace($this->host);

    $this->getJson("/api/host/places/{$place->id}")->assertStatus(401);
    $this->putJson("/api/host/places/{$place->id}", [])->assertStatus(401);
    $this->deleteJson("/api/host/places/{$place->id}")->assertStatus(401);
});

it('edits a listing and resubmits it for review, keeping untouched photos', function (): void {
    $place = apiEditablePlace($this->host, [
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ]);
    foreach (range(1, 5) as $i) {
        PlacePhoto::query()->create([
            'place_id' => $place->id,
            'path' => "p/{$i}.jpg",
            'sort_order' => $i - 1,
            'featured_order' => $i === 1 ? 0 : null,
        ]);
    }

    $this->actingAs($this->host, 'api')
        ->putJson("/api/host/places/{$place->id}", [
            'title_ar' => 'شاليه محدث',
            'place_type_id' => $place->place_type_id,
            'city_area_id' => $place->city_area_id,
            'price' => 1100,
            'check_in_time' => '16:00',
            'check_out_time' => '11:00',
            'max_guests' => 10,
            'location_url' => 'https://maps.google.com/?q=24.8,46.7',
        ])
        ->assertOk()
        ->assertJsonPath('data.title_ar', 'شاليه محدث')
        ->assertJsonPath('data.price', 1100)
        // Content changed → back to review and offline until re-approved.
        ->assertJsonPath('data.review_status', 'pending_review')
        ->assertJsonPath('data.status', 'inactive')
        // A details-only edit leaves the existing gallery untouched.
        ->assertJsonCount(5, 'data.photos');
});

it('soft-deletes a listing', function (): void {
    $place = apiEditablePlace($this->host);

    $this->actingAs($this->host, 'api')
        ->deleteJson("/api/host/places/{$place->id}")
        ->assertOk();

    expect(Place::withTrashed()->find($place->id)->trashed())->toBeTrue();

    // Gone from the API — the binding excludes trashed rows.
    $this->actingAs($this->host, 'api')
        ->getJson("/api/host/places/{$place->id}")
        ->assertNotFound();
});
