<?php

declare(strict_types=1);

use App\Enums\PlaceReviewStatus;
use App\Enums\UserRole;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;
use App\Models\UserNotification;

beforeEach(function (): void {
    $this->seed();
});

/**
 * Minimal valid payload for the host wizard's final submit. Tests override
 * specific keys per scenario (host_phone, draft_id, etc.).
 *
 * @return array<string, mixed>
 */
function placeSubmitPayload(array $overrides = []): array
{
    $data = array_merge([
        'title' => 'Test Place',
        'description' => 'A nice place by the lake.',
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'price' => 500,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'location_url' => 'https://maps.google.com/?q=24.7,46.6',
        // A place must have at least 5 images overall.
        'extra_image_paths' => [
            'places/uploads/1.jpg', 'places/uploads/2.jpg', 'places/uploads/3.jpg',
            'places/uploads/4.jpg', 'places/uploads/5.jpg',
        ],
    ], $overrides);

    // Wizard posts bilingual content — map single-field test values onto *_ar.
    foreach (['title', 'description', 'rules'] as $field) {
        if (array_key_exists($field, $data)) {
            $data["{$field}_ar"] = $data[$field];
            unset($data[$field]);
        }
    }

    return $data;
}

it('attaches a new place to an existing host phone when admin submits', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '599888777']);
    $host = User::factory()->create(['phone' => '512345001']);

    $this->actingAs($admin, 'api')
        ->post('/host-register', placeSubmitPayload(['host_phone' => '512345001']))
        ->assertRedirect();

    $place = Place::query()->latest('created_at')->first();
    expect($place->host_user_id)->toBe($host->id);
    expect($place->host_user_id)->not->toBe($admin->id);
    expect($place->review_status)->toBe(PlaceReviewStatus::PendingReview);
});

it('creates a new user shell when admin attaches a place to a previously-unknown phone', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '599888777']);

    expect(User::query()->where('phone', '512999000')->exists())->toBeFalse();

    $this->actingAs($admin, 'api')
        ->post('/host-register', placeSubmitPayload(['host_phone' => '512999000']))
        ->assertRedirect();

    $newHost = User::query()->where('phone', '512999000')->first();
    expect($newHost)->not->toBeNull();
    expect($newHost->role)->toBe(UserRole::User);

    $place = Place::query()->latest('created_at')->first();
    expect($place->host_user_id)->toBe($newHost->id);
});

it('ignores host_phone posted by a non-admin and uses the current user', function (): void {
    $regular = User::factory()->create(['phone' => '512345002', 'role' => UserRole::User->value]);
    $otherHost = User::factory()->create(['phone' => '512345003']);

    $this->actingAs($regular, 'api')
        ->post('/host-register', placeSubmitPayload(['host_phone' => '512345003']))
        ->assertRedirect();

    $place = Place::query()->latest('created_at')->first();
    expect($place->host_user_id)->toBe($regular->id);
    expect($place->host_user_id)->not->toBe($otherHost->id);
});

it('attaches the place to the admin themselves when host_phone is omitted', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '599888777']);

    $this->actingAs($admin, 'api')
        ->post('/host-register', placeSubmitPayload())  // no host_phone
        ->assertRedirect();

    $place = Place::query()->latest('created_at')->first();
    expect($place->host_user_id)->toBe($admin->id);
});

it('accepts human phone formats on submit and rejects genuinely invalid ones', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '599888777']);

    // Leading 0 normalizes to the national form and attaches correctly.
    $this->actingAs($admin, 'api')
        ->post('/host-register', placeSubmitPayload(['host_phone' => '0501203845']))
        ->assertSessionDoesntHaveErrors('host_phone');
    expect(User::query()->where('phone', '501203845')->exists())->toBeTrue();

    // International prefix normalizes too.
    $this->actingAs($admin, 'api')
        ->post('/host-register', placeSubmitPayload(['host_phone' => '+966501203846', 'title' => 'Intl format']))
        ->assertSessionDoesntHaveErrors('host_phone');
    expect(User::query()->where('phone', '501203846')->exists())->toBeTrue();

    // Not a Saudi mobile at all → still rejected on the field.
    $this->actingAs($admin, 'api')
        ->post('/host-register', placeSubmitPayload(['host_phone' => '412034859', 'title' => 'Bad phone']))
        ->assertSessionHasErrors('host_phone');
});

it('attaches an admin draft auto-save to the resolved host from the first save', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '599888777']);

    $this->actingAs($admin, 'api')
        ->postJson('/host-register/draft', [
            'host_phone' => '512999111',
            'place_type_id' => PlaceType::query()->first()->id,
        ])
        ->assertOk();

    $newHost = User::query()->where('phone', '512999111')->first();
    expect($newHost)->not->toBeNull();

    $draft = Place::query()->latest('created_at')->first();
    expect($draft->host_user_id)->toBe($newHost->id);
    expect($draft->review_status)->toBe(PlaceReviewStatus::Draft);
});

// ─── owner reassignment via admin EDIT ───────────────────────────────────────

/**
 * Minimal valid admin-edit payload (details-only: no photo fields, so the
 * 5-image minimum guard stays out of the way).
 *
 * @return array<string, mixed>
 */
function adminEditPayload(Place $place, array $overrides = []): array
{
    return array_merge([
        'title_ar' => $place->title_ar ?: 'مكان',
        'place_type_id' => $place->place_type_id,
        'city_area_id' => $place->city_area_id,
        'price' => 500,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'location_url' => 'https://maps.google.com/?q=24.7,46.6',
        'status' => 'inactive',
        'review_status' => 'pending_review',
    ], $overrides);
}

function adminOwnedPlace(User $owner): Place
{
    return Place::query()->create([
        'host_user_id' => $owner->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Reassign me',
        'title_ar' => 'مكان للنقل',
        'description' => 'x',
        'price' => 500,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'status' => 'inactive',
        'review_status' => 'pending_review',
    ]);
}

it('reassigns the place to a new owner phone on admin edit and notifies them like a first submission', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '599888001']);
    $newOwner = User::factory()->create(['phone' => '512345900']);
    $place = adminOwnedPlace($admin);

    $this->actingAs($admin, 'api')
        ->put(route('admin.places.update', $place), adminEditPayload($place, ['host_phone' => '512345900']))
        ->assertRedirect();

    expect($place->refresh()->host_user_id)->toBe($newOwner->id)
        // The new owner hears about it exactly like a first submission.
        ->and(UserNotification::query()
            ->where('user_id', $newOwner->id)
            ->where('type', 'place_submitted')
            ->count())->toBe(1);
});

it('keeps the owner and stays silent when the phone is unchanged on edit', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '599888002']);
    $owner = User::factory()->create(['phone' => '512345901']);
    $place = adminOwnedPlace($owner);

    $this->actingAs($admin, 'api')
        ->put(route('admin.places.update', $place), adminEditPayload($place, ['host_phone' => '512345901']))
        ->assertRedirect();

    expect($place->refresh()->host_user_id)->toBe($owner->id)
        ->and(UserNotification::query()
            ->where('user_id', $owner->id)
            ->where('type', 'place_submitted')
            ->count())->toBe(0);
});

it('creates a shell account when the edit phone is unknown and transfers to it', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '599888003']);
    $place = adminOwnedPlace($admin);

    $this->actingAs($admin, 'api')
        ->put(route('admin.places.update', $place), adminEditPayload($place, ['host_phone' => '512345902']))
        ->assertRedirect();

    $shell = User::query()->where('phone', '512345902')->first();
    expect($shell)->not->toBeNull()
        ->and($place->refresh()->host_user_id)->toBe($shell->id)
        ->and(UserNotification::query()
            ->where('user_id', $shell->id)
            ->where('type', 'place_submitted')
            ->count())->toBe(1);
});

it('leaves the owner untouched when host_phone is blank on edit', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '599888004']);
    $owner = User::factory()->create(['phone' => '512345903']);
    $place = adminOwnedPlace($owner);

    $this->actingAs($admin, 'api')
        ->put(route('admin.places.update', $place), adminEditPayload($place, ['host_phone' => '']))
        ->assertRedirect();

    expect($place->refresh()->host_user_id)->toBe($owner->id);
});

it('normalizes human phone formats before reassigning on edit', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '599888005']);
    $ownerA = User::factory()->create(['phone' => '512345904']);
    $ownerB = User::factory()->create(['phone' => '512345905']);

    // Leading zero (the way everyone types it).
    $placeA = adminOwnedPlace($admin);
    $this->actingAs($admin, 'api')
        ->put(route('admin.places.update', $placeA), adminEditPayload($placeA, ['host_phone' => '0512345904']))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors('host_phone');
    expect($placeA->refresh()->host_user_id)->toBe($ownerA->id);

    // Full international paste with spaces.
    $placeB = adminOwnedPlace($admin);
    $this->actingAs($admin, 'api')
        ->put(route('admin.places.update', $placeB), adminEditPayload($placeB, ['host_phone' => '+966 51 234 5905']))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors('host_phone');
    expect($placeB->refresh()->host_user_id)->toBe($ownerB->id);

    // Genuinely wrong numbers still fail loudly on the field.
    $placeC = adminOwnedPlace($admin);
    $this->actingAs($admin, 'api')
        ->put(route('admin.places.update', $placeC), adminEditPayload($placeC, ['host_phone' => '412345906']))
        ->assertSessionHasErrors('host_phone');
    expect($placeC->refresh()->host_user_id)->toBe($admin->id);
});
