<?php

declare(strict_types=1);

namespace App\Services\Place;

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\Place;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class PlaceService
{
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return Place::query()
            ->with(['host', 'type', 'cityArea.city'])
            ->withCount(['photos', 'attributeValues'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Places that belong to a specific host (the host's own list).
     *
     * @return Collection<int, Place>
     */
    public function forHost(User $host): Collection
    {
        return Place::query()
            ->where('host_user_id', $host->id)
            ->with(['type', 'cityArea.city', 'coverPhoto'])
            ->latest()
            ->get();
    }

    /**
     * Confirm a host's place — the final submit at the end of the wizard.
     * If a $draftId is provided and matches one of this host's Draft places,
     * we promote that record to PendingReview. Otherwise we create fresh.
     *
     * This pairs with {@see saveDraftForHost()}: the wizard auto-saves each
     * step as a Draft, then this method flips that Draft to PendingReview
     * once the host clicks "Create place" so we never end up with duplicate
     * rows for one wizard session.
     *
     * @param  array<string, mixed>  $data
     */
    public function createForHost(User $host, array $data, ?int $draftId = null): Place
    {
        $existing = null;
        if ($draftId !== null) {
            $existing = Place::query()
                ->where('id', $draftId)
                ->where('host_user_id', $host->id)
                ->where('review_status', PlaceReviewStatus::Draft->value)
                ->first();
        }

        $payload = [
            ...$data,
            'host_user_id' => $host->id,
            'status' => PlaceStatus::Inactive->value,
            'review_status' => PlaceReviewStatus::PendingReview->value,
        ];

        if ($existing) {
            $existing->update($payload);

            return $existing->refresh();
        }

        return Place::query()->create($payload);
    }

    /**
     * Upsert the host's in-progress draft. The wizard calls this every time
     * the host advances a step, so the server-side record exists from step 1
     * onward and survives a tab close. Status stays Inactive + review_status
     * stays Draft until the host hits the final "Create place" submit, which
     * uses {@see createForHost()} (well, the same Draft, just confirmed).
     *
     * @param  array<string, mixed>  $data  Any subset of place columns; nulls and missing keys are tolerated.
     */
    public function saveDraftForHost(User $host, array $data, ?int $draftId = null): Place
    {
        $draft = null;
        if ($draftId !== null) {
            $draft = Place::query()
                ->where('id', $draftId)
                ->where('host_user_id', $host->id)
                ->where('review_status', PlaceReviewStatus::Draft->value)
                ->first();
        }

        $payload = [
            ...$data,
            'host_user_id' => $host->id,
            'status' => PlaceStatus::Inactive->value,
            'review_status' => PlaceReviewStatus::Draft->value,
        ];

        if ($draft) {
            $draft->update($payload);

            return $draft->refresh();
        }

        return Place::query()->create($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Place $place, array $data): Place
    {
        $place->update($data);

        return $place->refresh();
    }

    public function setReviewStatus(Place $place, PlaceReviewStatus $status): Place
    {
        $place->update(['review_status' => $status->value]);

        return $place->refresh();
    }

    public function delete(Place $place): void
    {
        $place->delete();
    }
}
