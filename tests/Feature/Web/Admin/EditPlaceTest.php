<?php

declare(strict_types=1);

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Enums\UserRole;
use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceList;
use App\Models\PlaceType;
use App\Models\User;

beforeEach(function (): void {
    $this->seed();
    $this->admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '598000001']);
    $this->host = User::factory()->create(['phone' => '516000001']);
});

function adminPlace(User $host, array $attrs = []): Place
{
    return Place::query()->create(array_merge([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Admin editable place',
        'description' => 'x',
        'price' => 600,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ], $attrs));
}

/**
 * @return array<string, mixed>
 */
function adminWizardPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Admin edited title',
        'description' => 'Updated.',
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'price' => 800,
        'check_in_time' => '14:00',
        'check_out_time' => '11:00',
        'max_guests' => 5,
        'rules' => 'Quiet.',
        'location_url' => 'https://maps.google.com/?q=24.7,46.6',
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
        'rejection_reason' => null,
    ], $overrides);
}

it('renders the admin edit wizard pre-filled with the admin step', function (): void {
    $place = adminPlace($this->host, ['title' => 'Lakeview Chalet']);
    PlaceList::query()->create(['name_en' => 'Featured', 'name_ar' => 'مميز', 'sort_order' => 0]);

    $this->actingAs($this->admin, 'api')
        ->get("/admin/places/{$place->id}/edit")
        ->assertOk()
        ->assertSee('Lakeview Chalet')          // hydrated into the wizard's init JSON
        ->assertSee('إعدادات المشرف', false)    // admin-only "Admin settings" step
        ->assertSee('Featured');                // the curated list option
});

it('saves an admin edit without forcing re-review, syncing amenities/photos/lists', function (): void {
    $place = adminPlace($this->host);
    $list = PlaceList::query()->create(['name_en' => 'Picks', 'name_ar' => 'مختارات', 'sort_order' => 0]);
    $group = AttributeGroup::query()->create(['name_en' => 'Indoor', 'name_ar' => 'داخلي']);
    $amenity = Attribute::query()->create([
        'group_id' => $group->id, 'name_en' => 'Pool', 'name_ar' => 'مسبح',
        'icon' => '🏊', 'type' => 'boolean', 'photo_rule' => 'optional',
    ]);

    $this->actingAs($this->admin, 'api')
        ->put("/admin/places/{$place->id}", adminWizardPayload([
            'title' => 'Edited by admin',
            'lists' => [$list->id],
            'attributes' => [['attribute_id' => $amenity->id, 'value' => '1', 'description' => 'Heated']],
            'extra_image_paths' => [
                'places/uploads/a.jpg', 'places/uploads/b.jpg', 'places/uploads/c.jpg',
                'places/uploads/d.jpg', 'places/uploads/e.jpg',
            ],
            'featured' => ['extra_images.0'],
        ]))
        ->assertRedirect(route('admin.places.index'));

    $place->refresh()->load(['attributeValues', 'photos', 'lists']);
    expect($place->title)->toBe('Edited by admin')
        // Admin keeps status — NOT forced to PendingReview like the host edit.
        ->and($place->status)->toBe(PlaceStatus::Active)
        ->and($place->review_status)->toBe(PlaceReviewStatus::Approved)
        ->and($place->attributeValues)->toHaveCount(1)
        ->and($place->photos)->toHaveCount(5)
        ->and($place->lists->pluck('id')->all())->toBe([$list->id]);
});

it('saves the location link on an admin edit', function (): void {
    $place = adminPlace($this->host);

    $this->actingAs($this->admin, 'api')
        ->put("/admin/places/{$place->id}", adminWizardPayload(['location_url' => 'https://maps.app.goo.gl/adminEdit']))
        ->assertRedirect(route('admin.places.index'));

    expect($place->refresh()->location_url)->toBe('https://maps.app.goo.gl/adminEdit');
});

it('lets the admin change status + review from the admin step', function (): void {
    $place = adminPlace($this->host);

    $this->actingAs($this->admin, 'api')
        ->put("/admin/places/{$place->id}", adminWizardPayload([
            'status' => PlaceStatus::Inactive->value,
            'review_status' => PlaceReviewStatus::Rejected->value,
            'rejection_reason' => 'Photos too dark.',
        ]))
        ->assertRedirect();

    $place->refresh();
    expect($place->status)->toBe(PlaceStatus::Inactive)
        ->and($place->review_status)->toBe(PlaceReviewStatus::Rejected)
        ->and($place->rejection_reason)->toBe('Photos too dark.');
});

it('keeps a non-admin out of the admin edit + update', function (): void {
    $place = adminPlace($this->host);

    // The `admin` middleware redirects non-admins away rather than 403ing.
    $this->actingAs($this->host, 'api')->get("/admin/places/{$place->id}/edit")->assertRedirect();
    $this->actingAs($this->host, 'api')->put("/admin/places/{$place->id}", adminWizardPayload(['title' => 'Hacked']))->assertRedirect();

    expect($place->refresh()->title)->toBe('Admin editable place'); // untouched
});
