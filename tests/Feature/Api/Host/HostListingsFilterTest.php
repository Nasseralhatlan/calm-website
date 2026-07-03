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
    $this->host = User::factory()->create(['phone' => '514300001']);
});

function listingWithStatus(User $host, string $reviewStatus, string $status = 'inactive'): Place
{
    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Listing '.fake()->unique()->numerify('####'),
        'description' => 'Desc.',
        'price' => 500,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'checkout_next_day' => false,
        'max_guests' => 4,
        'status' => $status,
        'review_status' => $reviewStatus,
    ]);
}

it('filters listings by lifecycle status', function (): void {
    $draft = listingWithStatus($this->host, PlaceReviewStatus::Draft->value);
    $pending = listingWithStatus($this->host, PlaceReviewStatus::PendingReview->value);
    $active = listingWithStatus($this->host, PlaceReviewStatus::Approved->value, PlaceStatus::Active->value);
    $rejected = listingWithStatus($this->host, PlaceReviewStatus::Rejected->value);

    // No filter → everything, in one unpaginated response.
    $this->actingAs($this->host, 'api')
        ->getJson('/api/host/listings')
        ->assertOk()
        ->assertJsonCount(4, 'data.items')
        ->assertJsonMissingPath('data.pagination');

    $this->actingAs($this->host, 'api')
        ->getJson('/api/host/listings?status=draft')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $draft->id);

    $this->actingAs($this->host, 'api')
        ->getJson('/api/host/listings?status=pending_review')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $pending->id);

    $this->actingAs($this->host, 'api')
        ->getJson('/api/host/listings?status=rejected')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $rejected->id);

    // `active` filters on the live status column, not review_status.
    $this->actingAs($this->host, 'api')
        ->getJson('/api/host/listings?status=active')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $active->id);
});

it('rejects an unknown status', function (): void {
    $this->actingAs($this->host, 'api')
        ->getJson('/api/host/listings?status=bogus')
        ->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['status']]]);
});
