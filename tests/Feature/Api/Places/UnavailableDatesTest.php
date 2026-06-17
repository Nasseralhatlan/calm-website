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

/** Minimal Active+Approved place. */
function availabilityPlace(array $attrs = []): Place
{
    $host = User::factory()->create(['phone' => '511'.fake()->unique()->numerify('######')]);

    return Place::query()->create(array_merge([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Availability test place',
        'description' => 'Long-form description.',
        'price' => 500,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ], $attrs));
}

/** Block an inclusive [$start, $end] date range on the place. */
function block(Place $place, string $start, string $end, ?string $reason = null): void
{
    $place->blockings()->create([
        'start_date' => $start,
        'end_date' => $end,
        'reason' => $reason,
    ]);
}

it('expands a blocking into individual dates plus a merged range', function (): void {
    $place = availabilityPlace();
    block($place, '2030-06-10', '2030-06-12');

    $this->getJson("/api/places/{$place->id}/unavailable-dates?from=2030-06-01&to=2030-06-30")
        ->assertOk()
        ->assertJsonPath('status', 200)
        ->assertJsonPath('data.place_id', $place->id)
        ->assertJsonPath('data.from', '2030-06-01')
        ->assertJsonPath('data.to', '2030-06-30')
        ->assertJsonPath('data.unavailable_dates', ['2030-06-10', '2030-06-11', '2030-06-12'])
        ->assertJsonPath('data.unavailable_ranges', [
            ['start_date' => '2030-06-10', 'end_date' => '2030-06-12'],
        ]);
});

it('clamps a blocking that spills past the window edges', function (): void {
    $place = availabilityPlace();
    block($place, '2030-05-20', '2030-06-05'); // starts before `from`

    $this->getJson("/api/places/{$place->id}/unavailable-dates?from=2030-06-01&to=2030-06-30")
        ->assertOk()
        ->assertJsonPath('data.unavailable_dates', ['2030-06-01', '2030-06-02', '2030-06-03', '2030-06-04', '2030-06-05'])
        ->assertJsonPath('data.unavailable_ranges', [
            ['start_date' => '2030-06-01', 'end_date' => '2030-06-05'],
        ]);
});

it('merges overlapping and adjacent blockings without duplicate dates', function (): void {
    $place = availabilityPlace();
    block($place, '2030-06-10', '2030-06-12'); // overlaps the next
    block($place, '2030-06-11', '2030-06-13'); // overlap → 10..13
    block($place, '2030-06-14', '2030-06-15'); // adjacent to 13 → folds in

    $this->getJson("/api/places/{$place->id}/unavailable-dates?from=2030-06-01&to=2030-06-30")
        ->assertOk()
        ->assertJsonCount(6, 'data.unavailable_dates')
        ->assertJsonPath('data.unavailable_dates', [
            '2030-06-10', '2030-06-11', '2030-06-12', '2030-06-13', '2030-06-14', '2030-06-15',
        ])
        ->assertJsonPath('data.unavailable_ranges', [
            ['start_date' => '2030-06-10', 'end_date' => '2030-06-15'],
        ]);
});

it('keeps a one-day gap as two separate ranges', function (): void {
    $place = availabilityPlace();
    block($place, '2030-06-10', '2030-06-11');
    block($place, '2030-06-13', '2030-06-14'); // 12 is free → no merge

    $this->getJson("/api/places/{$place->id}/unavailable-dates?from=2030-06-01&to=2030-06-30")
        ->assertOk()
        ->assertJsonPath('data.unavailable_ranges', [
            ['start_date' => '2030-06-10', 'end_date' => '2030-06-11'],
            ['start_date' => '2030-06-13', 'end_date' => '2030-06-14'],
        ]);
});

it('excludes blockings entirely outside the requested window', function (): void {
    $place = availabilityPlace();
    block($place, '2030-07-10', '2030-07-12'); // after the window

    $this->getJson("/api/places/{$place->id}/unavailable-dates?from=2030-06-01&to=2030-06-30")
        ->assertOk()
        ->assertJsonPath('data.unavailable_dates', [])
        ->assertJsonPath('data.unavailable_ranges', []);
});

it('returns empty arrays when the place has no blockings', function (): void {
    $place = availabilityPlace();

    $this->getJson("/api/places/{$place->id}/unavailable-dates?from=2030-06-01&to=2030-06-30")
        ->assertOk()
        ->assertJsonPath('data.unavailable_dates', [])
        ->assertJsonPath('data.unavailable_ranges', []);
});

it('defaults the window to today and excludes past blockings', function (): void {
    $place = availabilityPlace();

    $past = now()->subDays(10)->toDateString();
    $pastEnd = now()->subDays(8)->toDateString();
    $futureStart = now()->addDays(5)->toDateString();
    $futureEnd = now()->addDays(7)->toDateString();

    block($place, $past, $pastEnd);
    block($place, $futureStart, $futureEnd);

    $body = $this->getJson("/api/places/{$place->id}/unavailable-dates")
        ->assertOk()
        ->json('data.unavailable_dates');

    expect($body)->not->toContain($past)
        ->and($body)->toContain($futureStart, $futureEnd);
});

it('never exposes the blocking reason', function (): void {
    $place = availabilityPlace();
    block($place, '2030-06-10', '2030-06-11', 'Reserved for owner family event');

    $raw = $this->getJson("/api/places/{$place->id}/unavailable-dates?from=2030-06-01&to=2030-06-30")
        ->assertOk()
        ->getContent();

    expect($raw)->not->toContain('Reserved for owner family event')
        ->and($raw)->not->toContain('reason');
});

it('rejects a malformed date with 422', function (): void {
    $place = availabilityPlace();

    $this->getJson("/api/places/{$place->id}/unavailable-dates?from=not-a-date")
        ->assertStatus(422);
});

it('rejects a `to` that precedes `from` with 422', function (): void {
    $place = availabilityPlace();

    $this->getJson("/api/places/{$place->id}/unavailable-dates?from=2030-06-30&to=2030-06-01")
        ->assertStatus(422);
});

it('returns 404 for a draft place', function (): void {
    $draft = availabilityPlace(['review_status' => PlaceReviewStatus::Draft->value]);

    $this->getJson("/api/places/{$draft->id}/unavailable-dates")
        ->assertStatus(404)
        ->assertJsonPath('message', 'Place not found.');
});

it('returns 404 for an inactive place', function (): void {
    $inactive = availabilityPlace(['status' => PlaceStatus::Inactive->value]);

    $this->getJson("/api/places/{$inactive->id}/unavailable-dates")
        ->assertStatus(404);
});
