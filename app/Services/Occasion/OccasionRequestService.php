<?php

declare(strict_types=1);

namespace App\Services\Occasion;

use App\Enums\OccasionRequestStatus;
use App\Models\OccasionRequest;
use App\Models\User;
use App\Services\Notification\OwnerNotifier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Occasion leads: capture one, list a guest's own, and let the events team work
 * the queue. Creating a lead also alerts the team — the guest is told on screen
 * that we'll call "within minutes".
 */
final class OccasionRequestService
{
    public function __construct(private readonly OwnerNotifier $owner) {}

    /**
     * Record a lead and alert the events team.
     *
     * @param  array<string, mixed>  $details  everything the wizard collected beyond the type
     */
    public function create(User $user, string $occasionType, array $details): OccasionRequest
    {
        $request = OccasionRequest::create([
            'user_id' => $user->id,
            'occasion_type' => $occasionType,
            'details' => $details,
            'status' => OccasionRequestStatus::New,
        ]);

        // Fire-and-forget: OwnerNotifier swallows its own failures, so a mail
        // hiccup never costs us the lead.
        $this->owner->occasionRequested($request->fresh('user') ?? $request);

        return $request;
    }

    /** The guest's own requests, newest first — "follow your request". */
    public function forUserPaginated(User $user): LengthAwarePaginator
    {
        return OccasionRequest::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(config('pagination.per_page'))
            ->withQueryString();
    }

    /**
     * The events team's queue, newest first. `$status` filters to one pipeline
     * stage; `$search` matches the guest's name or phone.
     */
    public function forAdminPaginated(?string $status = null, ?string $search = null): LengthAwarePaginator
    {
        return OccasionRequest::query()
            ->with('user:id,name,phone')
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->when($search !== null, fn ($q) => $q->whereHas(
                'user',
                fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"),
            ))
            ->latest()
            ->paginate(config('pagination.per_page'))
            ->withQueryString();
    }

    /** Move a lead along the pipeline. */
    public function updateStatus(OccasionRequest $request, OccasionRequestStatus $status): OccasionRequest
    {
        $request->update(['status' => $status]);

        return $request;
    }
}
