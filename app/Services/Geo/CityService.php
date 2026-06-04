<?php

declare(strict_types=1);

namespace App\Services\Geo;

use App\Models\City;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class CityService
{
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return City::query()
            ->with('country')
            ->withCount('areas')
            ->orderBy('name_en')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): City
    {
        return City::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(City $city, array $data): City
    {
        $city->update($data);

        return $city->refresh();
    }

    public function delete(City $city): void
    {
        $city->delete();
    }
}
