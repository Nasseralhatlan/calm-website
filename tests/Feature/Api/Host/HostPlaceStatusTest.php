<?php

declare(strict_types=1);

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;

beforeEach(function (): void {
    $this->seed();
    $this->host = User::factory()->create(['phone' => '514500001']);
});

function statusTogglePlace(User $host, string $reviewStatus, string $status = 'inactive'): Place
{
    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Tgl '.fake()->unique()->numerify('####'),
        'description' => 'Desc.',
        'price' => 800,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'checkout_next_day' => false,
        'max_guests' => 4,
        'status' => $status,
        'review_status' => $reviewStatus,
    ]);
}

it('pauses and reactivates an approved place', function (): void {
    $place = statusTogglePlace($this->host, PlaceReviewStatus::Approved->value, PlaceStatus::Active->value);

    $this->actingAs($this->host, 'api')
        ->patchJson("/api/host/places/{$place->id}/status", ['status' => 'inactive'])
        ->assertOk()
        ->assertJsonPath('data.status', 'inactive')
        ->assertJsonPath('data.review_status', 'approved');

    expect($place->fresh()->status)->toBe(PlaceStatus::Inactive);

    // Paused → gone from the public API (visible-gate on the detail endpoint).
    $this->getJson("/api/places/{$place->id}")->assertNotFound();

    // Approved places reactivate freely — no re-review round-trip.
    $this->actingAs($this->host, 'api')
        ->patchJson("/api/host/places/{$place->id}/status", ['status' => 'active'])
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    expect($place->fresh()->status)->toBe(PlaceStatus::Active);
    $this->getJson("/api/places/{$place->id}")->assertOk();
});

it('refuses to activate a place that is not approved', function (): void {
    foreach ([PlaceReviewStatus::Draft, PlaceReviewStatus::PendingReview, PlaceReviewStatus::Rejected] as $state) {
        $place = statusTogglePlace($this->host, $state->value);

        $this->actingAs($this->host, 'api')
            ->patchJson("/api/host/places/{$place->id}/status", ['status' => 'active'])
            ->assertStatus(422)
            ->assertJsonStructure(['data' => ['errors' => ['status']]]);

        expect($place->fresh()->status)->toBe(PlaceStatus::Inactive);
    }
});

it('always allows pausing regardless of review state', function (): void {
    $place = statusTogglePlace($this->host, PlaceReviewStatus::PendingReview->value);

    $this->actingAs($this->host, 'api')
        ->patchJson("/api/host/places/{$place->id}/status", ['status' => 'inactive'])
        ->assertOk()
        ->assertJsonPath('data.status', 'inactive');
});

it('validates the status value', function (): void {
    $place = statusTogglePlace($this->host, PlaceReviewStatus::Approved->value, PlaceStatus::Active->value);

    $this->actingAs($this->host, 'api')
        ->patchJson("/api/host/places/{$place->id}/status", ['status' => 'bogus'])
        ->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['status']]]);

    $this->actingAs($this->host, 'api')
        ->patchJson("/api/host/places/{$place->id}/status", [])
        ->assertStatus(422);
});

it('is owner-only', function (): void {
    $other = User::factory()->create(['phone' => '514500002']);
    $place = statusTogglePlace($this->host, PlaceReviewStatus::Approved->value, PlaceStatus::Active->value);

    $this->actingAs($other, 'api')
        ->patchJson("/api/host/places/{$place->id}/status", ['status' => 'inactive'])
        ->assertForbidden();

    expect($place->fresh()->status)->toBe(PlaceStatus::Active);
});

it('requires authentication', function (): void {
    $place = statusTogglePlace($this->host, PlaceReviewStatus::Approved->value, PlaceStatus::Active->value);

    $this->patchJson("/api/host/places/{$place->id}/status", ['status' => 'inactive'])->assertStatus(401);
});
