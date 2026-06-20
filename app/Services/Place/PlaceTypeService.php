<?php

declare(strict_types=1);

namespace App\Services\Place;

use App\Models\PlaceType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class PlaceTypeService
{
    public function paginate(?int $perPage = null): LengthAwarePaginator
    {
        return PlaceType::query()
            ->withCount('places')
            ->orderBy('name_en')
            ->paginate($perPage ?? config('pagination.per_page'));
    }

    /**
     * Active place types for the mobile API home screen — the type picker
     * grid above the listings.
     *
     * @return Collection<int, PlaceType>
     */
    public function activeForApi(): Collection
    {
        return PlaceType::query()->active()->orderBy('name_en')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PlaceType
    {
        return PlaceType::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PlaceType $placeType, array $data): PlaceType
    {
        $placeType->update($data);

        return $placeType->refresh();
    }

    public function delete(PlaceType $placeType): void
    {
        $placeType->delete();
    }
}
