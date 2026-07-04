<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceReview;
use App\Models\PlaceType;
use App\Models\User;

beforeEach(function (): void {
    $this->seed();
});

function reviewHost(): User
{
    return User::factory()->create(['phone' => '53'.fake()->unique()->numerify('#######')]);
}

function reviewGuest(): User
{
    return User::factory()->create(['phone' => '54'.fake()->unique()->numerify('#######'), 'name' => 'Nasser Alhatlan']);
}

function reviewPlace(User $host): Place
{
    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Review place '.fake()->unique()->numerify('###'),
        'description' => 'x', 'price' => 1000, 'check_in_time' => '15:00', 'check_out_time' => '12:00',
        'max_guests' => 4, 'status' => PlaceStatus::Active->value, 'review_status' => PlaceReviewStatus::Approved->value,
    ]);
}

function completedBooking(User $guest, Place $place, string $status = 'completed'): Booking
{
    return Booking::query()->create([
        'place_id' => $place->id, 'guest_user_id' => $guest->id, 'host_user_id' => $place->host_user_id,
        'booking_status' => $status, 'start_date' => '2026-06-10', 'end_date' => '2026-06-12',
        'check_in_time' => '15:00', 'check_out_time' => '12:00', 'guests' => 2,
        'booking_price' => 100000, 'quantity' => 2, 'host_gross_amount' => 200000,
        'commission_rate' => 10, 'commission_amount_ex_vat' => 20000, 'guest_vat_rate' => 15, 'guest_vat_amount' => 30000,
        'guest_total' => 230000, 'payout_status' => 'not_paid',
    ]);
}

// ─── Creating ────────────────────────────────────────────────────────────────

it('lets a guest review a completed booking → under_review', function (): void {
    $guest = reviewGuest();
    $place = reviewPlace(reviewHost());
    $booking = completedBooking($guest, $place);

    $this->actingAs($guest, 'api')
        ->postJson("/api/bookings/{$booking->id}/reviews", ['rate' => 5, 'comment' => 'Loved it'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'under_review')
        ->assertJsonPath('data.rate', 5);

    $review = PlaceReview::query()->first();
    expect($review->guest_user_id)->toBe($guest->id)
        ->and($review->place_id)->toBe($place->id)
        ->and($review->booking_id)->toBe($booking->id)
        ->and($review->status)->toBe(ReviewStatus::UnderReview);
});

it('rejects reviewing a non-completed booking', function (): void {
    $guest = reviewGuest();
    $booking = completedBooking($guest, reviewPlace(reviewHost()), BookingStatus::Confirmed->value);

    $this->actingAs($guest, 'api')
        ->postJson("/api/bookings/{$booking->id}/reviews", ['rate' => 4])
        ->assertStatus(422);
});

it("forbids reviewing someone else's booking", function (): void {
    $booking = completedBooking(reviewGuest(), reviewPlace(reviewHost()));

    $this->actingAs(reviewGuest(), 'api')
        ->postJson("/api/bookings/{$booking->id}/reviews", ['rate' => 4])
        ->assertForbidden();
});

it('allows only one active review per place per guest (even via another booking)', function (): void {
    $guest = reviewGuest();
    $place = reviewPlace(reviewHost());
    $b1 = completedBooking($guest, $place);
    $b2 = completedBooking($guest, $place); // second completed stay, same place

    $this->actingAs($guest, 'api')->postJson("/api/bookings/{$b1->id}/reviews", ['rate' => 5])->assertCreated();
    $this->actingAs($guest, 'api')->postJson("/api/bookings/{$b2->id}/reviews", ['rate' => 3])->assertStatus(422);

    expect(PlaceReview::query()->count())->toBe(1);
});

// ─── Deleting + re-adding ────────────────────────────────────────────────────

it('lets the guest delete then re-add a review (soft delete)', function (): void {
    $guest = reviewGuest();
    $place = reviewPlace(reviewHost());
    $booking = completedBooking($guest, $place);

    $id = $this->actingAs($guest, 'api')
        ->postJson("/api/bookings/{$booking->id}/reviews", ['rate' => 4])->assertCreated()->json('data.id');

    $this->actingAs($guest, 'api')->deleteJson("/api/reviews/{$id}")->assertOk();
    expect(PlaceReview::query()->whereKey($id)->exists())->toBeFalse()           // soft-deleted, gone from default scope
        ->and(PlaceReview::withTrashed()->whereKey($id)->exists())->toBeTrue();

    // Can review again now that the slot is free.
    $this->actingAs($guest, 'api')
        ->postJson("/api/bookings/{$booking->id}/reviews", ['rate' => 5])->assertCreated();
});

it('locks a blocked review — cannot delete or re-add', function (): void {
    $guest = reviewGuest();
    $place = reviewPlace(reviewHost());
    $booking = completedBooking($guest, $place);
    $review = PlaceReview::query()->create([
        'place_id' => $place->id, 'guest_user_id' => $guest->id, 'booking_id' => $booking->id,
        'rate' => 1, 'comment' => 'bad', 'status' => ReviewStatus::Blocked->value,
    ]);

    $this->actingAs($guest, 'api')->deleteJson("/api/reviews/{$review->id}")->assertForbidden();
    $this->actingAs($guest, 'api')->postJson("/api/bookings/{$booking->id}/reviews", ['rate' => 5])->assertStatus(422);
});

it("forbids deleting another guest's review", function (): void {
    $review = PlaceReview::query()->create([
        'place_id' => reviewPlace(reviewHost())->id, 'guest_user_id' => reviewGuest()->id,
        'rate' => 5, 'status' => ReviewStatus::Published->value,
    ]);

    $this->actingAs(reviewGuest(), 'api')->deleteJson("/api/reviews/{$review->id}")->assertForbidden();
});

// ─── Public visibility = published only ──────────────────────────────────────

it('shows only published reviews + rating on the public place detail', function (): void {
    $place = reviewPlace(reviewHost());
    PlaceReview::query()->create(['place_id' => $place->id, 'guest_user_id' => reviewGuest()->id, 'rate' => 5, 'status' => 'published']);
    PlaceReview::query()->create(['place_id' => $place->id, 'guest_user_id' => reviewGuest()->id, 'rate' => 1, 'status' => 'under_review']);
    PlaceReview::query()->create(['place_id' => $place->id, 'guest_user_id' => reviewGuest()->id, 'rate' => 1, 'status' => 'blocked']);

    $this->getJson("/api/places/{$place->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.reviews_recent')        // only the published one
        ->assertJsonPath('data.rating.count', 1)
        ->assertJsonPath('data.rating.avg', 5);
});

// ─── Host published reviews ──────────────────────────────────────────────────

it('returns the host published reviews with reviewer first name', function (): void {
    $host = reviewHost();
    $place = reviewPlace($host);
    PlaceReview::query()->create(['place_id' => $place->id, 'guest_user_id' => reviewGuest()->id, 'rate' => 5, 'status' => 'published']);
    PlaceReview::query()->create(['place_id' => $place->id, 'guest_user_id' => reviewGuest()->id, 'rate' => 2, 'status' => 'under_review']);

    $this->actingAs($host, 'api')
        ->getJson('/api/host/reviews')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.reviewer_name', 'Nasser')
        ->assertJsonPath('data.items.0.place.id', $place->id);
});

// ─── Booking exposes review + can_review ─────────────────────────────────────

it('exposes review + can_review on the guest bookings list', function (): void {
    $guest = reviewGuest();
    $place = reviewPlace(reviewHost());
    $booking = completedBooking($guest, $place);

    $this->actingAs($guest, 'api')->getJson('/api/bookings')
        ->assertOk()
        ->assertJsonPath('data.items.0.can_review', true)
        ->assertJsonPath('data.items.0.review', null);

    $this->actingAs($guest, 'api')->postJson("/api/bookings/{$booking->id}/reviews", ['rate' => 5])->assertCreated();

    $this->actingAs($guest, 'api')->getJson('/api/bookings')
        ->assertOk()
        ->assertJsonPath('data.items.0.can_review', false)
        ->assertJsonPath('data.items.0.review.status', 'under_review')
        ->assertJsonPath('data.items.0.review.rate', 5);
});

// ─── Admin moderation ────────────────────────────────────────────────────────

it('lets an admin change a review status', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '500000900']);
    $place = reviewPlace(reviewHost());
    $review = PlaceReview::query()->create([
        'place_id' => $place->id, 'guest_user_id' => reviewGuest()->id, 'rate' => 5, 'status' => 'under_review',
    ]);

    $this->actingAs($admin, 'api')->get('/admin/reviews')->assertOk();

    $this->actingAs($admin, 'api')
        ->post("/admin/reviews/{$review->id}/status", ['status' => 'published'])
        ->assertRedirect()->assertSessionHas('status');
    expect($review->refresh()->status)->toBe(ReviewStatus::Published);

    // Now it counts toward the public rating.
    $this->getJson("/api/places/{$place->id}")->assertOk()->assertJsonPath('data.rating.count', 1);
});
