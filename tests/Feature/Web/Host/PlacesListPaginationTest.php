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
});

function hostPlace(User $host, string $title): Place
{
    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => $title,
        'description' => 'x',
        'price' => 500,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ]);
}

it('paginates the host places listing', function (): void {
    config(['pagination.per_page' => 2]);
    $host = User::factory()->create(['phone' => '514900001']);

    hostPlace($host, 'Alpha'); // oldest
    $this->travel(1)->minutes();
    hostPlace($host, 'Bravo');
    $this->travel(1)->minutes();
    hostPlace($host, 'Charlie'); // newest

    // Page 1: newest two (Charlie, Bravo), and a navigator to page 2.
    $this->actingAs($host, 'api')
        ->get('/my-places')
        ->assertOk()
        ->assertSee('page=2')             // paginator rendered
        ->assertSee('Charlie')
        ->assertSee('Bravo')
        ->assertDontSee('Alpha');         // pushed to page 2

    // Page 2: the oldest place.
    $this->actingAs($host, 'api')
        ->get('/my-places?page=2')
        ->assertOk()
        ->assertSee('Alpha')
        ->assertDontSee('Charlie');
});
