<?php

declare(strict_types=1);

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Enums\UserRole;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;

beforeEach(function (): void {
    $this->seed();
});

function showPlace(User $host, array $attrs = []): Place
{
    return Place::query()->create(array_merge([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Showable place',
        'description' => 'A nice place.',
        'price' => 500,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ], $attrs));
}

it('shows a live place to the public with no status banner', function (): void {
    $host = User::factory()->create(['phone' => '515000001']);
    $place = showPlace($host);

    $this->get("/places/{$place->id}")
        ->assertOk()
        ->assertSee('Showable place')
        ->assertDontSee('معاينة', false);
});

it('404s a non-live place for the public', function (): void {
    $host = User::factory()->create(['phone' => '515000002']);
    $place = showPlace($host, ['review_status' => PlaceReviewStatus::PendingReview->value]);

    $this->get("/places/{$place->id}")->assertNotFound();
});

it('shows the owner their non-live place with a status banner', function (): void {
    $host = User::factory()->create(['phone' => '515000003']);
    $place = showPlace($host, [
        'status' => PlaceStatus::Inactive->value,
        'review_status' => PlaceReviewStatus::PendingReview->value,
    ]);

    $this->actingAs($host, 'api')
        ->get("/places/{$place->id}")
        ->assertOk()
        ->assertSee('معاينة', false)            // banner present
        ->assertSee('قيد المراجعة', false)      // review: pending
        ->assertSee('موقوف', false);            // status: inactive
});

it('lets an admin view any place with the banner', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '599000020']);
    $host = User::factory()->create(['phone' => '515000004']);
    $place = showPlace($host, ['review_status' => PlaceReviewStatus::Rejected->value, 'status' => PlaceStatus::Inactive->value]);

    $this->actingAs($admin, 'api')
        ->get("/places/{$place->id}")
        ->assertOk()
        ->assertSee('معاينة', false)     // banner present
        ->assertSee('مرفوض', false);     // review: rejected
});

it('does not show the banner to a different signed-in user', function (): void {
    $host = User::factory()->create(['phone' => '515000005']);
    $other = User::factory()->create(['phone' => '515000006']);
    $place = showPlace($host); // live, so visible

    $this->actingAs($other, 'api')
        ->get("/places/{$place->id}")
        ->assertOk()
        ->assertDontSee('معاينة', false);
});
