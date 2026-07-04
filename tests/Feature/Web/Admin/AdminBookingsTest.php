<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;
use App\Models\UserNotification;

beforeEach(function (): void {
    $this->seed();
    $this->admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '598100001']);
    $this->host = User::factory()->create(['phone' => '516100001']);
    $this->guest = User::factory()->create(['phone' => '517100001']);
});

function bkPlace(User $host): Place
{
    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Bookings place', 'price' => 1000, 'check_in_time' => '15:00', 'check_out_time' => '12:00',
        'max_guests' => 4, 'status' => PlaceStatus::Active->value, 'review_status' => PlaceReviewStatus::Approved->value,
    ]);
}

function bk(Place $place, User $guest, array $attrs = []): Booking
{
    return Booking::query()->create(array_merge([
        'place_id' => $place->id,
        'guest_user_id' => $guest->id,
        'host_user_id' => $place->host_user_id,
        'booking_status' => BookingStatus::Confirmed->value,
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'guests' => 2, 'nights' => 2, 'stay_amount' => 200000,
        'commission_rate' => 10, 'commission_amount' => 20000, 'guest_vat_rate' => 15, 'guest_vat_amount' => 30000,
        'guest_total' => 230000, 'payout_status' => 'not_paid',
    ], $attrs));
}

it('lists all bookings across every host (admin fetches everything)', function (): void {
    // Two different hosts — the admin list must show BOTH, unscoped.
    $mine = bk(bkPlace($this->host), $this->guest);

    $otherHost = User::factory()->create(['phone' => '516100077']);
    $otherGuest = User::factory()->create(['phone' => '517100077']);
    $theirs = bk(bkPlace($otherHost), $otherGuest);

    $this->actingAs($this->admin, 'api')
        ->get('/admin/bookings')
        ->assertOk()
        ->assertSee($mine->reference)
        ->assertSee($theirs->reference);   // another host's booking is visible to admin
});

it('blocks a non-admin from the admin bookings page', function (): void {
    // The mobile host scoping is separate; this admin web page is admin-only.
    $this->actingAs($this->host, 'api')
        ->get('/admin/bookings')
        ->assertRedirect('/profile');
});

it('searches bookings by guest phone, host phone, place id and reference', function (): void {
    $place = bkPlace($this->host);
    $mine = bk($place, $this->guest);

    $otherHost = User::factory()->create(['phone' => '516100099']);
    $otherGuest = User::factory()->create(['phone' => '517100099']);
    $otherPlace = bkPlace($otherHost);
    bk($otherPlace, $otherGuest);

    // by guest phone — leading-zero tolerant (admin may type 0517...)
    $this->actingAs($this->admin, 'api')->get('/admin/bookings?q=0517100001')
        ->assertOk()->assertSee($mine->reference);
    $this->actingAs($this->admin, 'api')->get('/admin/bookings?q=517100001')
        ->assertOk()->assertSee($mine->reference);
    $this->actingAs($this->admin, 'api')->get('/admin/bookings?q=516100001')
        ->assertOk()->assertSee($mine->reference);
    $this->actingAs($this->admin, 'api')->get("/admin/bookings?q={$place->id}")
        ->assertOk()->assertSee($mine->reference);
    $this->actingAs($this->admin, 'api')->get("/admin/bookings?q={$mine->reference}")
        ->assertOk()->assertSee($mine->reference);
});

it('filters the bookings list by status', function (): void {
    $place = bkPlace($this->host);
    $confirmed = bk($place, $this->guest, ['booking_status' => BookingStatus::Confirmed->value]);
    $completed = bk($place, $this->guest, ['booking_status' => BookingStatus::Completed->value]);
    $cancelled = bk($place, $this->guest, ['booking_status' => BookingStatus::CanceledByAdmin->value, 'canceled_at' => now()]);

    // Confirmed filter shows only the confirmed booking.
    $this->actingAs($this->admin, 'api')->get('/admin/bookings?status=confirmed')
        ->assertOk()->assertSee($confirmed->reference)
        ->assertDontSee($completed->reference)->assertDontSee($cancelled->reference);

    // Cancelled filter covers the canceled_* group.
    $this->actingAs($this->admin, 'api')->get('/admin/bookings?status=cancelled')
        ->assertOk()->assertSee($cancelled->reference)->assertDontSee($confirmed->reference);
});

it('shows a booking detail page', function (): void {
    $place = bkPlace($this->host);
    $booking = bk($place, $this->guest);

    $this->actingAs($this->admin, 'api')
        ->get("/admin/bookings/{$booking->id}")
        ->assertOk()
        ->assertSee($booking->reference)
        ->assertSee('Bookings place');
});

it('cancels a confirmed booking as admin and notifies guest + host', function (): void {
    $place = bkPlace($this->host);
    $booking = bk($place, $this->guest);

    $this->actingAs($this->admin, 'api')
        ->post("/admin/bookings/{$booking->id}/cancel", ['actor' => 'admin'])
        ->assertRedirect(route('admin.bookings.show', $booking));

    $booking->refresh();
    expect($booking->booking_status)->toBe(BookingStatus::CanceledByAdmin)
        ->and($booking->canceled_at)->not->toBeNull();

    // Both parties notified.
    expect(UserNotification::query()->where('user_id', $this->guest->id)->where('type', 'booking_canceled_by_admin')->count())->toBe(1)
        ->and(UserNotification::query()->where('user_id', $this->host->id)->where('type', 'booking_canceled_by_admin')->count())->toBe(1);
});

it('cancels a confirmed booking as host (attributed to the host)', function (): void {
    $place = bkPlace($this->host);
    $booking = bk($place, $this->guest);

    $this->actingAs($this->admin, 'api')
        ->post("/admin/bookings/{$booking->id}/cancel", ['actor' => 'host'])
        ->assertRedirect();

    expect($booking->refresh()->booking_status)->toBe(BookingStatus::CanceledByHost);
    expect(UserNotification::query()->where('user_id', $this->guest->id)->where('type', 'booking_canceled_by_host')->count())->toBe(1);
});

it('refuses to cancel a non-confirmed booking', function (): void {
    $place = bkPlace($this->host);
    $booking = bk($place, $this->guest, ['booking_status' => BookingStatus::Completed->value]);

    $this->actingAs($this->admin, 'api')
        ->post("/admin/bookings/{$booking->id}/cancel", ['actor' => 'admin'])
        ->assertStatus(422);

    expect($booking->refresh()->booking_status)->toBe(BookingStatus::Completed);
});

it('validates the actor and blocks non-admins', function (): void {
    $place = bkPlace($this->host);
    $booking = bk($place, $this->guest);

    // bad actor
    $this->actingAs($this->admin, 'api')
        ->from(route('admin.bookings.show', $booking))
        ->post("/admin/bookings/{$booking->id}/cancel", ['actor' => 'nobody'])
        ->assertSessionHasErrors('actor');

    // non-admin blocked by the admin middleware (redirected away, booking untouched)
    $this->actingAs($this->host, 'api')
        ->post("/admin/bookings/{$booking->id}/cancel", ['actor' => 'admin'])
        ->assertStatus(302);
    expect($booking->refresh()->booking_status)->toBe(BookingStatus::Confirmed);
});
