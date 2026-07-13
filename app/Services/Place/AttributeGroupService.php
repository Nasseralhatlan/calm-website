<?php

declare(strict_types=1);

namespace App\Services\Place;

use App\Models\AttributeGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class AttributeGroupService
{
    public function paginate(?int $perPage = null): LengthAwarePaginator
    {
        return AttributeGroup::query()
            ->withCount('attributes')
            ->ordered()
            ->paginate($perPage ?? config('pagination.per_page'));
    }

    /**
     * The full amenity catalog for the place wizard: every group in
     * admin-controlled order, each with its attributes in the same order.
     * Powers the mobile app's create/edit flow (the web wizard loads the
     * equivalent set server-side in Host\PlacesController::wizardCatalog).
     *
     * @return Collection<int, AttributeGroup>
     */
    public function catalog(): Collection
    {
        return AttributeGroup::query()
            ->with(['attributes' => fn ($q) => $q->ordered()])
            ->ordered()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): AttributeGroup
    {
        // An unchecked admin checkbox sends nothing — default explicitly.
        $data['is_standalone'] ??= false;

        return AttributeGroup::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AttributeGroup $group, array $data): AttributeGroup
    {
        $data['is_standalone'] ??= false;

        $group->update($data);

        return $group->refresh();
    }

    public function delete(AttributeGroup $group): void
    {
        $group->delete();
    }
}
