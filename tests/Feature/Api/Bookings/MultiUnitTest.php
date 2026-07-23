<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\Booking;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\PlaceUnit;
use App\Models\User;
use App\Services\Booking\BookingService;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->seed();
});

/** Active+Approved place with N identical named units. */
function multiUnitPlace(int $units, array $attrs = []): Place
{
    $host = User::factory()->create(['phone' => '517'.fake()->unique()->numerify('######')]);

    $place = Place::query()->create(array_merge([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Multi-unit place',
        'description' => 'Desc.',
        'price' => 1000,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ], $attrs));

    // range(1, 0) is [1, 0] in PHP — guard so 0 really means no units.
    foreach ($units > 0 ? range(1, $units) : [] as $i) {
        PlaceUnit::query()->create(['place_id' => $place->id, 'name' => "وحدة {$i}", 'sort_order' => $i]);
    }

    return $place;
}

function unitBooking(Place $place, array $attrs = []): Booking
{
    $guest = User::factory()->create(['phone' => '518'.fake()->unique()->numerify('######')]);

    return Booking::query()->create(array_merge([
        'place_id' => $place->id,
        'guest_user_id' => $guest->id,
        'host_user_id' => $place->host_user_id,
        'booking_status' => BookingStatus::Confirmed->value,
        'start_date' => now()->addDays(10)->toDateString(),
        'end_date' => now()->addDays(12)->toDateString(),
        'guests' => 2,
        'nights' => 3,
        'stay_amount' => 300000,
        'commission_rate' => 10,
        'commission_amount' => 30000,
        'vat_rate' => 15,
        'vat_amount' => 45000,
        'total_amount' => 345000,
        'payout_status' => 'not_paid',
    ], $attrs));
}

function fakeMoyasarInvoiceCreate(): void
{
    Http::fake([
        'api.moyasar.com/v1/invoices' => Http::response([
            'id' => 'inv_multiunit', 'status' => 'initiated', 'amount' => 0,
            'url' => 'https://checkout.moyasar.com/invoices/inv_multiunit', 'metadata' => [],
        ]),
    ]);
}

it('keeps a date open until every unit is booked, then blocks it', function (): void {
    $place = multiUnitPlace(3);
    $window = '?from='.now()->addDays(9)->toDateString().'&to='.now()->addDays(14)->toDateString();
    $day = now()->addDays(11)->toDateString();

    // Two of three units taken → the day is still open.
    $firstUnit = $place->units()->first();
    unitBooking($place, ['place_unit_id' => $firstUnit->id]);
    unitBooking($place, ['place_unit_id' => $place->units()->get()[1]->id]);

    $open = $this->getJson("/api/places/{$place->id}/unavailable-dates{$window}")->assertOk()->json('data.unavailable_dates');
    expect($open)->not->toContain($day);

    // Third unit taken → now the day blocks.
    unitBooking($place, ['place_unit_id' => $place->units()->get()[2]->id]);

    $closed = $this->getJson("/api/places/{$place->id}/unavailable-dates{$window}")->assertOk()->json('data.unavailable_dates');
    expect($closed)->toContain($day);
});

it('assigns each new booking the first free unit, in host order', function (): void {
    fakeMoyasarInvoiceCreate();
    $place = multiUnitPlace(3);
    $in = now()->addDays(20)->toDateString();
    $out = now()->addDays(22)->toDateString();

    $service = app(BookingService::class);

    $first = $service->create(User::factory()->create(['phone' => '519811001']), $place, $in, $out, 2);
    $second = $service->create(User::factory()->create(['phone' => '519811002']), $place, $in, $out, 2);

    $names = $place->units()->get();
    expect($first->unit->name)->toBe($names[0]->name)
        ->and($second->unit->name)->toBe($names[1]->name);

    // A booking on OTHER dates starts from the first unit again.
    $elsewhere = $service->create(
        User::factory()->create(['phone' => '519811003']),
        $place,
        now()->addDays(40)->toDateString(),
        now()->addDays(41)->toDateString(),
        2,
    );
    expect($elsewhere->unit->name)->toBe($names[0]->name);
});

it('frees a unit when its booking is cancelled and reuses it', function (): void {
    fakeMoyasarInvoiceCreate();
    $place = multiUnitPlace(2);
    $in = now()->addDays(20)->toDateString();
    $out = now()->addDays(22)->toDateString();
    $units = $place->units()->get();

    $a = unitBooking($place, ['start_date' => $in, 'end_date' => $out, 'place_unit_id' => $units[0]->id]);
    unitBooking($place, ['start_date' => $in, 'end_date' => $out, 'place_unit_id' => $units[1]->id]);

    // Fully booked → creation refused.
    $service = app(BookingService::class);
    $guest = User::factory()->create(['phone' => '519811004']);
    expect(fn () => $service->create($guest, $place, $in, $out, 2))
        ->toThrow(HttpException::class);

    // Cancel one → its unit is free again and gets reassigned.
    $a->update(['booking_status' => BookingStatus::CanceledByAdmin->value]);

    $replacement = $service->create($guest, $place, $in, $out, 2);
    expect($replacement->unit->id)->toBe($units[0]->id);
});

it('single-unit places (no unit rows) behave exactly as before', function (): void {
    fakeMoyasarInvoiceCreate();
    $place = multiUnitPlace(0);
    $in = now()->addDays(20)->toDateString();
    $out = now()->addDays(22)->toDateString();

    $service = app(BookingService::class);
    $booking = $service->create(User::factory()->create(['phone' => '519811005']), $place, $in, $out, 2);
    expect($booking->place_unit_id)->toBeNull();

    // One booking is enough to block the dates.
    expect(fn () => $service->create(User::factory()->create(['phone' => '519811006']), $place, $in, $out, 2))
        ->toThrow(HttpException::class);
});

it('a host manual block closes the place even with free units', function (): void {
    $place = multiUnitPlace(3);
    $day = now()->addDays(30)->toDateString();

    $place->blockings()->create([
        'start_date' => $day,
        'end_date' => $day,
    ]);

    // Window widened past the block: SQLite stores casted dates with a time
    // suffix, so an exact from==to boundary string-compares past the edge
    // (test-only artifact; MySQL DATE columns don't carry the suffix).
    $from = now()->addDays(29)->toDateString();
    $to = now()->addDays(31)->toDateString();

    $dates = $this->getJson("/api/places/{$place->id}/unavailable-dates?from={$from}&to={$to}")
        ->assertOk()->json('data.unavailable_dates');
    expect($dates)->toContain($day);
});

it('carries the unit on the host dashboard highlights and calendar-day endpoints', function (): void {
    $place = multiUnitPlace(2);
    $unit = $place->units()->first();
    // Arriving within the highlights window (next 7 days) and covering a
    // fixed calendar day, so both endpoints pick it up.
    unitBooking($place, [
        'place_unit_id' => $unit->id,
        'start_date' => now()->addDays(2)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
    ]);

    $this->actingAs($place->host, 'api')
        ->getJson('/api/host/bookings/highlights')
        ->assertOk()
        ->assertJsonPath('data.upcoming.0.unit.name', $unit->name);

    $this->actingAs($place->host, 'api')
        ->getJson('/api/host/calendar/day?date='.now()->addDays(3)->toDateString())
        ->assertOk()
        ->assertJsonPath('data.bookings.0.unit.name', $unit->name);
});

it('exposes units_count on the host listings so cards can badge multi-unit places', function (): void {
    $multi = multiUnitPlace(4);
    multiUnitPlace(0, ['host_user_id' => $multi->host_user_id, 'title' => 'Single-unit place']);

    $items = $this->actingAs($multi->host, 'api')
        ->getJson('/api/host/listings')
        ->assertOk()
        ->json('data.items');

    $byTitle = collect($items)->keyBy('title');
    expect($byTitle['Multi-unit place']['units_count'])->toBe(4)
        ->and($byTitle['Single-unit place']['units_count'])->toBe(0);
});

it('exposes the assigned unit to the host bookings list but not the guest list', function (): void {
    $place = multiUnitPlace(2);
    $unit = $place->units()->first();
    $booking = unitBooking($place, ['place_unit_id' => $unit->id]);

    $this->actingAs($place->host, 'api')
        ->getJson('/api/host/bookings')
        ->assertOk()
        ->assertJsonPath('data.items.0.unit.name', $unit->name);

    $this->actingAs($booking->guest, 'api')
        ->getJson('/api/bookings')
        ->assertOk()
        ->assertJsonMissingPath('data.items.0.unit');
});
