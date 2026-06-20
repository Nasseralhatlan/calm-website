<?php

declare(strict_types=1);

namespace App\Services\Place;

use App\Enums\BookingStatus;
use App\Enums\ReviewStatus;
use App\Models\Booking;
use App\Models\PlaceReview;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ReviewService
{
    /**
     * A guest reviews the place of a completed booking. One ACTIVE review per
     * (place, guest) — a blocked review is still active, so it locks re-review.
     */
    public function createForBooking(Booking $booking, int $rate, ?string $comment): PlaceReview
    {
        if ($booking->booking_status !== BookingStatus::Completed) {
            abort(422, 'You can only review a completed stay.');
        }

        $alreadyReviewed = PlaceReview::query()
            ->where('place_id', $booking->place_id)
            ->where('guest_user_id', $booking->guest_user_id)
            ->exists();

        if ($alreadyReviewed) {
            abort(422, 'You have already reviewed this place.');
        }

        return PlaceReview::query()->create([
            'place_id' => $booking->place_id,
            'guest_user_id' => $booking->guest_user_id,
            'booking_id' => $booking->id,
            'rate' => $rate,
            'comment' => $comment,
            'status' => ReviewStatus::UnderReview->value,
        ]);
    }

    /** Guest soft-deletes their own review — blocked reviews can't be removed. */
    public function deleteOwn(PlaceReview $review): void
    {
        if ($review->status === ReviewStatus::Blocked) {
            abort(403, 'A blocked review cannot be deleted.');
        }

        $review->delete();
    }

    /** Published reviews across the host's places. */
    public function publishedForHost(User $host, ?int $perPage = null): LengthAwarePaginator
    {
        return PlaceReview::query()
            ->published()
            ->whereHas('place', fn ($q) => $q->where('host_user_id', $host->id))
            ->with(['guest', 'place.coverPhoto'])
            ->latest()
            ->paginate($perPage ?? config('pagination.per_page'))
            ->withQueryString();
    }

    /** Admin moderation list — every status, optional status + place-title filter. */
    public function paginateForAdmin(?string $status = null, ?string $search = null, ?int $perPage = null): LengthAwarePaginator
    {
        return PlaceReview::query()
            ->with(['guest', 'place'])
            ->when($status, fn ($q, string $s) => $q->where('status', $s))
            ->when($search, fn ($q, string $term) => $q->whereHas('place', fn ($p) => $p->where('title', 'like', '%'.$term.'%')))
            ->latest()
            ->paginate($perPage ?? config('pagination.per_page'))
            ->withQueryString();
    }

    public function setStatus(PlaceReview $review, ReviewStatus $status): PlaceReview
    {
        $review->update(['status' => $status->value]);

        return $review->refresh();
    }
}
