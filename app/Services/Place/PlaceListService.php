<?php

declare(strict_types=1);

namespace App\Services\Place;

use App\Models\Place;
use App\Models\PlaceList;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Curated place lists used by the admin to build landing-page sections.
 * Single source of truth for paginate/CRUD + the attach/detach pivot ops.
 */
final class PlaceListService
{
    public function paginate(?int $perPage = null): LengthAwarePaginator
    {
        return PlaceList::query()
            ->withCount('places')
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->paginate($perPage ?? config('pagination.per_page'));
    }

    /** Fetch a list ready for editing — with its current members + sort order. */
    public function findForEdit(string $id): ?PlaceList
    {
        return PlaceList::query()
            ->with(['places' => fn ($q) => $q->with('type', 'cityArea.city', 'coverPhoto')])
            ->find($id);
    }

    /**
     * Active lists for the mobile home screen, each with its visible member
     * places hydrated for PlaceResource. Lists with zero visible members are
     * dropped (the app shouldn't render an empty section row). When a
     * $viewer is provided, each place gets the `liked_by_me` exists flag for
     * the heart icon.
     *
     * @return Collection<int, PlaceList>
     */
    public function activeForApi(PlaceService $places, ?User $viewer = null): Collection
    {
        return PlaceList::query()
            ->active()
            ->with(['places' => function ($q) use ($places, $viewer): void {
                $q->visible();
                $places->eagerHomeFields($q, $viewer);
            }])
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get()
            ->filter(fn (PlaceList $list) => $list->places->isNotEmpty())
            ->values();
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): PlaceList
    {
        return PlaceList::query()->create($data);
    }

    /** @param  array<string, mixed>  $data */
    public function update(PlaceList $list, array $data): PlaceList
    {
        $list->update($data);

        return $list->refresh();
    }

    public function delete(PlaceList $list): void
    {
        $list->delete();
    }

    /**
     * Attach a place to the list at the next available sort position. No-op
     * if the place is already a member (unique pivot key prevents duplicates).
     */
    public function addPlace(PlaceList $list, Place $place): void
    {
        if ($list->places()->where('places.id', $place->id)->exists()) {
            return;
        }

        $nextSort = (int) ($list->places()->max('place_list_items.sort_order') ?? -1) + 1;
        $list->places()->attach($place->id, ['sort_order' => $nextSort]);
    }

    public function removePlace(PlaceList $list, Place $place): void
    {
        $list->places()->detach($place->id);
    }

    /**
     * Replace the members' sort order in one transaction so reorder drag-and-
     * drops are atomic. Pass an array of place ids in the desired order.
     *
     * @param  list<string>  $orderedPlaceIds
     */
    public function reorder(PlaceList $list, array $orderedPlaceIds): void
    {
        DB::transaction(function () use ($list, $orderedPlaceIds): void {
            foreach ($orderedPlaceIds as $index => $placeId) {
                $list->places()->updateExistingPivot($placeId, ['sort_order' => $index]);
            }
        });
    }
}
