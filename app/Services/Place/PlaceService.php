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
     * Create a new place owned by the given host. New places start as
     * inactive drafts pending review — exactly the same default the admin
     * would land on if creating via the admin panel.
     *
     * @param  array<string, mixed>  $data
     */
    public function createForHost(User $host, array $data): Place
    {
        return Place::query()->create([
            ...$data,
            'host_user_id' => $host->id,
            'status' => PlaceStatus::Inactive->value,
            'review_status' => PlaceReviewStatus::Draft->value,
        ]);
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
