<?php

declare(strict_types=1);

namespace App\Services\Place;

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\Place;
use App\Services\Notification\NotificationService;

/**
 * The admin review workflow lives here so the controller stays at one
 * service call per action. Each public method returns the *next* pending
 * place (or null if the queue is empty) so the controller can drive the
 * "Approve and review next" flow without extra round-trips.
 */
final class PlaceReviewService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Approve the submission. Flips review_status to Approved and turns the
     * listing on (status = Active) so guests can start booking. The host is
     * notified (SMS + push + in-app).
     */
    public function approve(Place $place): ?Place
    {
        $place->update([
            'review_status' => PlaceReviewStatus::Approved->value,
            'status' => PlaceStatus::Active->value,
            'rejection_reason' => null,
            'reviewed_at' => now(),
        ]);

        $this->notifications->placeApproved($place);

        return $this->nextAfter($place);
    }

    /**
     * Reject the submission with admin feedback. The host sees this above the
     * wizard when they resume the draft so they know what to fix. Listing
     * stays Inactive. We deliberately reset `last_step` to 1 so when the
     * host opens "Edit & resubmit" from My Places they land at the start of
     * the wizard (with the rejection banner front and centre) and re-walk
     * every step — all their previous selections survive untouched, the
     * wizard just makes them review the whole submission top-to-bottom.
     */
    public function reject(Place $place, string $reason): ?Place
    {
        $place->update([
            'review_status' => PlaceReviewStatus::Rejected->value,
            'status' => PlaceStatus::Inactive->value,
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
            'last_step' => 1,
        ]);

        $this->notifications->placeRejected($place, $reason);

        return $this->nextAfter($place);
    }

    /**
     * Skip this one without changing its state. Returns the next pending
     * place, excluding the one we just skipped.
     */
    public function skipAfter(Place $place): ?Place
    {
        return $this->nextAfter($place);
    }

    /**
     * The next pending row AFTER the current one in queue order
     * (updated_at, id), wrapping to the front when nothing lies ahead.
     * The forward cursor is what makes "skip" walk 1→2→3→1 — always
     * restarting from the oldest would bounce the admin back to the first
     * skipped place after every skip. Approve/reject bump the current row's
     * updated_at to now, so for them the cursor naturally wraps to the
     * oldest pending — same target as before.
     */
    public function nextAfter(Place $place): ?Place
    {
        $pending = Place::query()
            ->where('review_status', PlaceReviewStatus::PendingReview->value)
            ->whereKeyNot($place->id);

        $ahead = (clone $pending)
            ->where(fn ($q) => $q
                ->where('updated_at', '>', $place->updated_at)
                ->orWhere(fn ($tie) => $tie
                    ->where('updated_at', $place->updated_at)
                    ->where('id', '>', $place->id)))
            ->orderBy('updated_at')
            ->orderBy('id')
            ->first();

        return $ahead ?? (clone $pending)->orderBy('updated_at')->orderBy('id')->first();
    }
}
