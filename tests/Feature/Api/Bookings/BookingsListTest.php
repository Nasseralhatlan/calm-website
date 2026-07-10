<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\Booking;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;

beforeEach(function (): void {
    $this->seed();
});

function listPlace(User $host, array $attrs = []): Place
{
    return Place::query()->create(array_merge([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'List '.fake()->unique()->numerify('chalet-####'),
        'description' => 'Desc.',
        'price' => 1000,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ], $attrs));
}

function listBooking(Place $place, User $guest, array $attrs = []): Booking
{
    return Booking::query()->create(array_merge([
        'place_id' => $place->id,
        'guest_user_id' => $guest->id,
        'host_user_id' => $place->host_user_id,
        'booking_status' => BookingStatus::Confirmed->value,
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'guests' => 2,
        'nights' => 2,
        'stay_amount' => 200000,
        'commission_rate' => 10,
        'commission_amount' => 20000,
        'vat_rate' => 15,
        'vat_amount' => 30000,
        'total_amount' => 230000,
        'payout_status' => 'not_paid',
    ], $attrs));
}

it('lists the guest\'s bookings with place details + price + status', function (): void {
    $guest = User::factory()->create(['phone' => '519000001']);
    $host = User::factory()->create(['phone' => '519000002']);
    $place = listPlace($host, [
        'title' => 'Beach Stay',
        'title_ar' => 'استراحة الشاطئ',
        'title_en' => 'Beach Stay',
    ]);

    listBooking($place, $guest);

    $this->actingAs($guest, 'api')
        ->getJson('/api/bookings')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonCount(1, 'data.items')
        // Status, dates, price, and place details on each item.
        ->assertJsonPath('data.items.0.status', 'confirmed')
        ->assertJsonPath('data.items.0.start_date', now()->addDays(3)->toDateString())
        ->assertJsonPath('data.items.0.pricing.total', 2300)
        ->assertJsonPath('data.items.0.place.title', 'Beach Stay')
        // Canonical title + bilingual pair so the app can localize per viewer.
        ->assertJsonPath('data.items.0.place.title_ar', 'استراحة الشاطئ')
        ->assertJsonPath('data.items.0.place.title_en', 'Beach Stay')
        ->assertJsonStructure(['data' => ['items' => [[
            'id', 'status', 'start_date', 'end_date', 'guests',
            'pricing' => ['total', 'total_minor'],
            'place' => ['id', 'title', 'title_ar', 'title_en', 'cover_photo_url', 'type', 'city'],
        ]]]]);
});

it('orders the list for UX: pending → confirmed(soonest) → completed(recent) → cancelled', function (): void {
    config(['pagination.per_page' => 20]);
    $guest = User::factory()->create(['phone' => '519000030']);
    $host = User::factory()->create(['phone' => '519000031']);
    $place = listPlace($host);

    // Created in deliberately scrambled order so created_at can't explain the result.
    $confirmedFar = listBooking($place, $guest, ['start_date' => now()->addDays(20)->toDateString(), 'end_date' => now()->addDays(21)->toDateString()]);
    $completedOld = listBooking($place, $guest, ['booking_status' => BookingStatus::Completed->value, 'start_date' => now()->subDays(30)->toDateString(), 'end_date' => now()->subDays(29)->toDateString()]);
    $pendingSoon = listBooking($place, $guest, ['booking_status' => BookingStatus::PendingPayment->value, 'start_date' => now()->addDays(2)->toDateString(), 'end_date' => now()->addDays(3)->toDateString(), 'expires_at' => now()->addMinutes(10)]);
    $cancelledOld = listBooking($place, $guest, ['booking_status' => BookingStatus::CanceledByGuest->value, 'canceled_at' => now()->subDays(9)]);
    $confirmedSoon = listBooking($place, $guest, ['start_date' => now()->addDays(4)->toDateString(), 'end_date' => now()->addDays(5)->toDateString()]);
    $completedRecent = listBooking($place, $guest, ['booking_status' => BookingStatus::Completed->value, 'start_date' => now()->subDays(3)->toDateString(), 'end_date' => now()->subDays(2)->toDateString()]);
    $cancelledRecent = listBooking($place, $guest, ['booking_status' => BookingStatus::CanceledByHost->value, 'canceled_at' => now()->subDay()]);

    $this->actingAs($guest, 'api')
        ->getJson('/api/bookings')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 7)
        ->assertJsonPath('data.items.0.id', $pendingSoon->id)       // 1. pending hold (must act)
        ->assertJsonPath('data.items.1.id', $confirmedSoon->id)     // 2. confirmed, nearest start_date
        ->assertJsonPath('data.items.2.id', $confirmedFar->id)      //    confirmed, later start_date
        ->assertJsonPath('data.items.3.id', $completedRecent->id)   // 3. completed, most recent end_date
        ->assertJsonPath('data.items.4.id', $completedOld->id)      //    completed, older
        ->assertJsonPath('data.items.5.id', $cancelledRecent->id)   // 4. cancelled, most recent canceled_at
        ->assertJsonPath('data.items.6.id', $cancelledOld->id);     //    cancelled, older
});

it('excludes expired bookings from the guest list', function (): void {
    $guest = User::factory()->create(['phone' => '519000020']);
    $host = User::factory()->create(['phone' => '519000021']);
    $place = listPlace($host);

    $kept = listBooking($place, $guest, ['booking_status' => BookingStatus::Confirmed->value]);
    listBooking($place, $guest, ['booking_status' => BookingStatus::Expired->value]); // hidden

    $this->actingAs($guest, 'api')
        ->getJson('/api/bookings')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $kept->id);
});

it('reveals the place location link only for confirmed and completed bookings', function (): void {
    $url = 'https://maps.google.com/?q=24.7,46.6';
    $guest = User::factory()->create(['phone' => '519000040']);
    $host = User::factory()->create(['phone' => '519000041']);
    $place = listPlace($host, ['location_url' => $url]);

    // Sort order: pending_payment → confirmed → completed → cancelled.
    listBooking($place, $guest, ['booking_status' => BookingStatus::PendingPayment->value]);
    listBooking($place, $guest, ['booking_status' => BookingStatus::Confirmed->value]);
    listBooking($place, $guest, ['booking_status' => BookingStatus::Completed->value, 'end_date' => now()->subDay()->toDateString()]);
    listBooking($place, $guest, ['booking_status' => BookingStatus::CanceledByHost->value, 'canceled_at' => now()->subDay()]);

    $this->actingAs($guest, 'api')
        ->getJson('/api/bookings')
        ->assertOk()
        ->assertJsonPath('data.items.0.status', 'pending_payment')
        ->assertJsonPath('data.items.0.place.location_url', null)   // hidden before payment
        ->assertJsonPath('data.items.1.status', 'confirmed')
        ->assertJsonPath('data.items.1.place.location_url', $url)   // unlocked
        ->assertJsonPath('data.items.2.status', 'completed')
        ->assertJsonPath('data.items.2.place.location_url', $url)   // unlocked
        ->assertJsonPath('data.items.3.status', 'canceled_by_host')
        ->assertJsonPath('data.items.3.place.location_url', null);  // hidden after cancellation
});

it('only returns the authenticated guest\'s bookings', function (): void {
    $guest = User::factory()->create(['phone' => '519000003']);
    $other = User::factory()->create(['phone' => '519000004']);
    $host = User::factory()->create(['phone' => '519000005']);
    $place = listPlace($host);

    listBooking($place, $guest);
    listBooking($place, $other);

    $this->actingAs($guest, 'api')
        ->getJson('/api/bookings')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1);
});

it('paginates the bookings list', function (): void {
    config(['pagination.per_page' => 1]); // page size is server-controlled now, not ?per_page=
    $guest = User::factory()->create(['phone' => '519000006']);
    $host = User::factory()->create(['phone' => '519000007']);
    $place = listPlace($host);

    listBooking($place, $guest);
    $this->travel(1)->minutes();
    $latest = listBooking($place, $guest);

    $this->actingAs($guest, 'api')
        ->getJson('/api/bookings')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $latest->id)
        ->assertJsonPath('data.pagination.per_page', 1)
        ->assertJsonPath('data.pagination.total', 2)
        ->assertJsonPath('data.pagination.has_more', true);
});

it('requires authentication to list bookings', function (): void {
    $this->getJson('/api/bookings')->assertStatus(401);
});

it('exposes the refund block only on cancelled PAID bookings', function (): void {
    $guest = User::factory()->create(['phone' => '514100031']);
    $place = listPlace(User::factory()->create(['phone' => '514100032']));

    $refunded = listBooking($place, $guest, [
        'booking_status' => BookingStatus::CanceledByAdmin->value,
        'payment_status' => 'paid',
        'canceled_at' => now(),
    ]);
    $cancelledUnpaid = listBooking($place, $guest, [
        'booking_status' => BookingStatus::CanceledByHost->value,
        'payment_status' => 'initiated',
        'canceled_at' => now(),
        'start_date' => now()->addDays(10)->toDateString(),
        'end_date' => now()->addDays(11)->toDateString(),
    ]);
    $confirmedPaid = listBooking($place, $guest, [
        'payment_status' => 'paid',
        'start_date' => now()->addDays(20)->toDateString(),
        'end_date' => now()->addDays(21)->toDateString(),
    ]);

    $items = $this->actingAs($guest, 'api')
        ->getJson('/api/bookings')
        ->assertOk()
        ->json('data.items');

    $byId = collect($items)->keyBy('id');

    // Cancelled + paid → full-refund policy: refunded = the guest's total.
    // (JSON round-trips whole floats as ints, hence loose equality.)
    expect($byId[$refunded->id]['refund'])->toEqual([
        'refunded' => true, 'amount' => 2300, 'amount_minor' => 230000,
    ]);
    // Cancelled but never paid / still confirmed → no refund key at all.
    expect($byId[$cancelledUnpaid->id])->not->toHaveKey('refund')
        ->and($byId[$confirmedPaid->id])->not->toHaveKey('refund');
});
