<?php

declare(strict_types=1);

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Enums\UserRole;
use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;

/** A throwaway boolean amenity to attach in tests. */
function testAmenity(): Attribute
{
    $group = AttributeGroup::query()->create(['name_en' => 'Indoor', 'name_ar' => 'داخلي']);

    return Attribute::query()->create([
        'group_id' => $group->id,
        'name_en' => 'WiFi',
        'name_ar' => 'واي فاي',
        'icon' => '📶',
        'type' => 'boolean',
        'photo_rule' => 'none',
    ]);
}

beforeEach(function (): void {
    $this->seed();
});

/** An Active+Approved place owned by the given host. */
function editablePlace(User $host, array $attrs = []): Place
{
    return Place::query()->create(array_merge([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Original title',
        'description' => 'Original description.',
        'price' => 500,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'rules' => 'No smoking.',
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ], $attrs));
}

/**
 * Minimal valid update payload. Override per scenario.
 *
 * @return array<string, mixed>
 */
function editPayload(array $overrides = []): array
{
    $data = array_merge([
        'title' => 'Updated title',
        'description' => 'Updated description.',
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'price' => 750,
        'check_in_time' => '14:00',
        'check_out_time' => '11:00',
        'max_guests' => 6,
        'rules' => 'Quiet after 10pm.',
        'location_url' => 'https://maps.google.com/?q=24.7,46.6',
    ], $overrides);

    // The wizard posts bilingual content (title_ar/_en, …); map the single-field
    // test value onto *_ar so canonical title/description/rules still derive.
    foreach (['title', 'description', 'rules'] as $field) {
        if (array_key_exists($field, $data)) {
            $data["{$field}_ar"] = $data[$field];
            unset($data[$field]);
        }
    }

    return $data;
}

it('shows the edit form to the place owner pre-filled', function (): void {
    $host = User::factory()->create(['phone' => '513000001']);
    $place = editablePlace($host, ['title' => 'Lakeview Chalet']);

    $this->actingAs($host, 'api')
        ->get("/my-places/{$place->id}/edit")
        ->assertOk()
        ->assertSee('Lakeview Chalet');
});

it('updates the details and resubmits the place for review', function (): void {
    $host = User::factory()->create(['phone' => '513000002']);
    $place = editablePlace($host);

    $this->actingAs($host, 'api')
        ->put("/my-places/{$place->id}", editPayload(['title' => 'Brand New Title', 'price' => 999]))
        ->assertRedirect(route('user.places'))
        ->assertSessionHas('status');

    $place->refresh();
    expect($place->title)->toBe('Brand New Title');
    expect($place->price)->toBe(999);
    // The core requirement: editing pushes the listing back to pending review…
    expect($place->review_status)->toBe(PlaceReviewStatus::PendingReview);
    // …and offline until an admin re-approves it.
    expect($place->status)->toBe(PlaceStatus::Inactive);
});

it('saves the pasted location link on update', function (): void {
    $host = User::factory()->create(['phone' => '513000020']);
    $place = editablePlace($host);

    $this->actingAs($host, 'api')
        ->put("/my-places/{$place->id}", editPayload(['location_url' => 'https://maps.google.com/?q=24.7,46.6']))
        ->assertRedirect();

    expect($place->refresh()->location_url)->toBe('https://maps.google.com/?q=24.7,46.6');
});

it('rejects an invalid location link on update', function (): void {
    $host = User::factory()->create(['phone' => '513000021']);
    $place = editablePlace($host);

    $this->actingAs($host, 'api')
        ->from("/my-places/{$place->id}/edit")
        ->put("/my-places/{$place->id}", editPayload(['location_url' => 'not a url']))
        ->assertSessionHasErrors('location_url');
});

it('pre-fills the saved location link in the edit form', function (): void {
    $host = User::factory()->create(['phone' => '513000024']);
    $place = editablePlace($host, ['location_url' => 'https://maps.app.goo.gl/abc123XYZ']);

    // The init payload JSON-escapes slashes (https:\/\/…), so assert on the
    // unique tail to confirm the saved value is hydrated into the edit form.
    $this->actingAs($host, 'api')
        ->get("/my-places/{$place->id}/edit")
        ->assertOk()
        ->assertSee('abc123XYZ');
});

it('requires a location link on update', function (): void {
    $host = User::factory()->create(['phone' => '513000022']);
    $place = editablePlace($host);

    $this->actingAs($host, 'api')
        ->from("/my-places/{$place->id}/edit")
        ->put("/my-places/{$place->id}", editPayload(['location_url' => '']))
        ->assertSessionHasErrors('location_url');
});

it('clears stale rejection feedback when a place is edited', function (): void {
    $host = User::factory()->create(['phone' => '513000003']);
    $place = editablePlace($host, ['rejection_reason' => 'Old reason']);

    $this->actingAs($host, 'api')
        ->put("/my-places/{$place->id}", editPayload())
        ->assertRedirect();

    expect($place->refresh()->rejection_reason)->toBeNull();
});

it('can edit a place that is already pending review', function (): void {
    $host = User::factory()->create(['phone' => '513000004']);
    $place = editablePlace($host, [
        'status' => PlaceStatus::Inactive->value,
        'review_status' => PlaceReviewStatus::PendingReview->value,
    ]);

    $this->actingAs($host, 'api')
        ->put("/my-places/{$place->id}", editPayload(['title' => 'Tweaked']))
        ->assertRedirect();

    $place->refresh();
    expect($place->title)->toBe('Tweaked');
    expect($place->review_status)->toBe(PlaceReviewStatus::PendingReview);
});

it('validates required fields on update', function (): void {
    $host = User::factory()->create(['phone' => '513000005']);
    $place = editablePlace($host);

    $this->actingAs($host, 'api')
        ->put("/my-places/{$place->id}", editPayload(['title' => '', 'price' => 'free']))
        ->assertSessionHasErrors(['title_ar', 'price']);

    // Untouched — a failed validation never reaches the service.
    $place->refresh();
    expect($place->title)->toBe('Original title');
    expect($place->review_status)->toBe(PlaceReviewStatus::Approved);
});

it('forbids editing another host\'s place', function (): void {
    $owner = User::factory()->create(['phone' => '513000006']);
    $intruder = User::factory()->create(['phone' => '513000007']);
    $place = editablePlace($owner);

    $this->actingAs($intruder, 'api')
        ->get("/my-places/{$place->id}/edit")
        ->assertForbidden();

    $this->actingAs($intruder, 'api')
        ->put("/my-places/{$place->id}", editPayload())
        ->assertForbidden();

    // Owner's listing untouched.
    expect($place->refresh()->title)->toBe('Original title');
});

it('lets an admin edit any host\'s place', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '599000002']);
    $host = User::factory()->create(['phone' => '513000008']);
    $place = editablePlace($host);

    $this->actingAs($admin, 'api')
        ->put("/my-places/{$place->id}", editPayload(['title' => 'Admin edited']))
        ->assertRedirect();

    expect($place->refresh()->title)->toBe('Admin edited');
});

it('adds amenities on edit', function (): void {
    $host = User::factory()->create(['phone' => '513000010']);
    $place = editablePlace($host);
    $amenity = testAmenity();

    $this->actingAs($host, 'api')
        ->put("/my-places/{$place->id}", editPayload([
            'attributes' => [
                ['attribute_id' => $amenity->id, 'value' => '1', 'description' => 'Fast fibre'],
            ],
        ]))
        ->assertRedirect();

    $place->load('attributeValues');
    expect($place->attributeValues)->toHaveCount(1);
    expect($place->attributeValues->first()->attribute_id)->toBe($amenity->id);
});

it('clears all amenities when none are submitted', function (): void {
    $host = User::factory()->create(['phone' => '513000011']);
    $place = editablePlace($host);
    $amenity = testAmenity();
    $place->attributeValues()->create(['attribute_id' => $amenity->id, 'value' => '1']);

    expect($place->attributeValues()->count())->toBe(1);

    $this->actingAs($host, 'api')
        ->put("/my-places/{$place->id}", editPayload())  // no attributes key
        ->assertRedirect();

    expect($place->attributeValues()->count())->toBe(0);
});

it('syncs photos and the cover on edit', function (): void {
    $host = User::factory()->create(['phone' => '513000012']);
    $place = editablePlace($host);

    $this->actingAs($host, 'api')
        ->put("/my-places/{$place->id}", editPayload([
            'extra_image_paths' => [
                'places/uploads/a.jpg', 'places/uploads/b.jpg', 'places/uploads/c.jpg',
                'places/uploads/d.jpg', 'places/uploads/e.jpg',
            ],
            'featured' => ['extra_images.0'],
        ]))
        ->assertRedirect();

    $place->load(['photos', 'coverPhoto']);
    expect($place->photos)->toHaveCount(5);
    expect($place->coverPhoto?->path)->toBe('places/uploads/a.jpg');
});
