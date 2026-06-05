<?php

declare(strict_types=1);

namespace App\Services\Geo;

use App\Models\CityArea;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class CityAreaService
{
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return CityArea::query()
            ->with('city.country')
            ->orderBy('name_en')
            ->paginate($perPage);
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

    public function delete(CityArea $cityArea): void
    {
        $cityArea->delete();
    }
}
