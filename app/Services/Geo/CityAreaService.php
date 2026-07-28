<?php

declare(strict_types=1);

namespace App\Services\Geo;

use App\Models\CityArea;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

final class CityAreaService
{
    public function paginate(?int $perPage = null): LengthAwarePaginator
    {
        return CityArea::query()
            ->with('city.country')
            ->orderBy('name_en')
            ->paginate($perPage ?? config('pagination.per_page'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CityArea
    {
        return CityArea::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CityArea $cityArea, array $data): CityArea
    {
        $cityArea->update($data);

        return $cityArea->refresh();
    }

    /**
     * places.city_area_id is ON DELETE RESTRICT — deleting an area that still
     * has places would 500 with a raw FK error, so refuse with a readable one.
     */
    public function delete(CityArea $cityArea): void
    {
        $placesCount = $cityArea->places()->count();

        if ($placesCount > 0) {
            throw ValidationException::withMessages([
                'city_area' => __('Cannot delete area ":name" — :count place(s) still use it. Move or delete those places first.', [
                    'name' => $cityArea->name_en,
                    'count' => $placesCount,
                ]),
            ]);
        }

        $cityArea->delete();
    }
}
