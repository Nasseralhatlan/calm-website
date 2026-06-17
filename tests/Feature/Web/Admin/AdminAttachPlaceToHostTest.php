<?php

declare(strict_types=1);

use App\Enums\PlaceReviewStatus;
use App\Enums\UserRole;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;

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
    return array_merge([
        'title' => 'Test Place',
        'description' => 'A nice place by the lake.',
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'price' => 500,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
    ], $overrides);
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

it('rejects an admin submit with a malformed host_phone', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '599888777']);

    // Leading 0 → invalid.
    $this->actingAs($admin, 'api')
        ->post('/host-register', placeSubmitPayload(['host_phone' => '0501203845']))
        ->assertSessionHasErrors('host_phone');

    // International prefix → invalid.
    $this->actingAs($admin, 'api')
        ->post('/host-register', placeSubmitPayload(['host_phone' => '+966501203845']))
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
