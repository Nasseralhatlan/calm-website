<?php

declare(strict_types=1);

namespace App\Services\Place;

use App\Models\PlaceType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PlaceTypeService
{
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return PlaceType::query()
            ->withCount('places')
            ->orderBy('name_en')
            ->paginate($perPage);
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
