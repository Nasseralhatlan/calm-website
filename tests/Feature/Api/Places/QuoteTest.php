<?php

declare(strict_types=1);

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\Setting;
use App\Models\User;

beforeEach(function (): void {
    $this->seed();
});

/** An Active+Approved place with controllable pricing. */
function quotePlace(array $attrs = []): Place
{
    $host = User::factory()->create(['phone' => '514'.fake()->unique()->numerify('######')]);

    return Place::query()->create(array_merge([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Quote test place',
        'description' => 'Desc.',
        'price' => 1000,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ], $attrs));
}

it('prices an inclusive day range, falling back to the base price', function (): void {
    $place = quotePlace(); // base 1000, all per-day columns 0 → fall back to base
    $checkIn = now()->addDays(3)->toDateString();
    $checkOut = now()->addDays(6)->toDateString(); // 4 inclusive days

    $this->getJson("/api/places/{$place->id}/quote?check_in={$checkIn}&check_out={$checkOut}")
        ->assertOk()
        ->assertJsonPath('data.place_id', $place->id)
        ->assertJsonPath('data.days', 4)
        ->assertJsonPath('data.currency', 'SAR')
        ->assertJsonPath('data.bookable', true)
        ->assertJsonPath('data.dates_available', true)
        ->assertJsonCount(4, 'data.breakdown')
        ->assertJsonPath('data.pricing.subtotal', 4000)
        // Guest pays subtotal + VAT (15% of subtotal). Commission is host-side.
        ->assertJsonPath('data.pricing.vat', 600)           // 4000 × 15%
        ->assertJsonPath('data.pricing.total', 4600)        // 4000 + 600
        ->assertJsonPath('data.pricing.total_minor', 460000);
});

it('uses the per-weekday price when the host set one', function (): void {
    $place = quotePlace(['price' => 1000, 'price_friday' => 5000]);
    $friday = now()->next(Carbon\Carbon::FRIDAY)->toDateString();

    $this->getJson("/api/places/{$place->id}/quote?check_in={$friday}&check_out={$friday}")
        ->assertOk()
        ->assertJsonPath('data.days', 1)
        ->assertJsonPath('data.breakdown.0.weekday', 'friday')
        ->assertJsonPath('data.breakdown.0.price', 5000)
        ->assertJsonPath('data.pricing.subtotal', 5000)
        ->assertJsonPath('data.pricing.total', 5750); // 5000 + 750 vat
});

it('marks the stay unavailable when a day in the range is blocked', function (): void {
    $place = quotePlace();
    $checkIn = now()->addDays(3);
    $checkOut = now()->addDays(6);
    $blocked = now()->addDays(4)->toDateString();

    $place->blockings()->create(['start_date' => $blocked, 'end_date' => $blocked]);

    $this->getJson("/api/places/{$place->id}/quote?check_in={$checkIn->toDateString()}&check_out={$checkOut->toDateString()}")
        ->assertOk()
        ->assertJsonPath('data.dates_available', false)
        ->assertJsonPath('data.bookable', false)
        ->assertJsonPath('data.unavailable_dates', [$blocked]);
});

it('flags when the party exceeds max_guests', function (): void {
    $place = quotePlace(['max_guests' => 4]);
    $checkIn = now()->addDays(3)->toDateString();
    $checkOut = now()->addDays(4)->toDateString();

    $this->getJson("/api/places/{$place->id}/quote?check_in={$checkIn}&check_out={$checkOut}&guests=6")
        ->assertOk()
        ->assertJsonPath('data.guests', 6)
        ->assertJsonPath('data.guests_ok', false)
        ->assertJsonPath('data.bookable', false)
        // Dates are still free — only the party size is the problem.
        ->assertJsonPath('data.dates_available', true);

    $this->getJson("/api/places/{$place->id}/quote?check_in={$checkIn}&check_out={$checkOut}&guests=4")
        ->assertOk()
        ->assertJsonPath('data.guests_ok', true)
        ->assertJsonPath('data.bookable', true);
});

it('handles a single-day stay (check_in === check_out)', function (): void {
    $place = quotePlace();
    $day = now()->addDays(5)->toDateString();

    $this->getJson("/api/places/{$place->id}/quote?check_in={$day}&check_out={$day}")
        ->assertOk()
        ->assertJsonPath('data.days', 1)
        ->assertJsonPath('data.pricing.subtotal', 1000)
        ->assertJsonPath('data.pricing.total', 1150); // 1000 + 150 vat
});

it('reads the VAT rate from settings (commission does not touch the guest total)', function (): void {
    Setting::query()->updateOrCreate(['key' => 'commission_percentage'], ['value' => '20']);
    Setting::query()->updateOrCreate(['key' => 'vat_percentage'], ['value' => '20']);

    $place = quotePlace(); // base 1000
    $checkIn = now()->addDays(3)->toDateString();
    $checkOut = now()->addDays(4)->toDateString(); // 2 inclusive days → 2000

    $this->getJson("/api/places/{$place->id}/quote?check_in={$checkIn}&check_out={$checkOut}")
        ->assertOk()
        ->assertJsonPath('data.pricing.subtotal', 2000)
        ->assertJsonPath('data.pricing.vat_percentage', 20)
        ->assertJsonPath('data.pricing.vat', 400)     // 2000 × 20%
        ->assertJsonPath('data.pricing.total', 2400); // 2000 + 400 (commission 20% excluded)
});

it('falls back to 15% VAT when the setting is missing', function (): void {
    Setting::query()->whereIn('key', ['commission_percentage', 'vat_percentage'])->delete();

    $place = quotePlace(); // base 1000
    $checkIn = now()->addDays(3)->toDateString();
    $checkOut = now()->addDays(6)->toDateString(); // 4 days → 4000

    $this->getJson("/api/places/{$place->id}/quote?check_in={$checkIn}&check_out={$checkOut}")
        ->assertOk()
        ->assertJsonPath('data.pricing.vat_percentage', 15)
        ->assertJsonPath('data.pricing.vat', 600)     // 4000 × 15%
        ->assertJsonPath('data.pricing.total', 4600); // 4000 + 600
});

it('rejects a check-in date in the past', function (): void {
    $place = quotePlace();

    $this->getJson("/api/places/{$place->id}/quote?check_in=".now()->subDay()->toDateString().'&check_out='.now()->addDay()->toDateString())
        ->assertStatus(422);
});

it('rejects a check-out before check-in', function (): void {
    $place = quotePlace();

    $this->getJson("/api/places/{$place->id}/quote?check_in=".now()->addDays(5)->toDateString().'&check_out='.now()->addDays(2)->toDateString())
        ->assertStatus(422);
});

it('rejects a stay longer than a year', function (): void {
    $place = quotePlace();

    $this->getJson("/api/places/{$place->id}/quote?check_in=".now()->addDay()->toDateString().'&check_out='.now()->addDays(400)->toDateString())
        ->assertStatus(422);
});

it('requires both dates', function (): void {
    $place = quotePlace();

    $this->getJson("/api/places/{$place->id}/quote")
        ->assertStatus(422);
});

it('returns 404 for a non-visible place', function (): void {
    $draft = quotePlace(['review_status' => PlaceReviewStatus::Draft->value]);
    $checkIn = now()->addDays(3)->toDateString();
    $checkOut = now()->addDays(4)->toDateString();

    $this->getJson("/api/places/{$draft->id}/quote?check_in={$checkIn}&check_out={$checkOut}")
        ->assertStatus(404)
        ->assertJsonPath('message', 'Place not found.');
});
