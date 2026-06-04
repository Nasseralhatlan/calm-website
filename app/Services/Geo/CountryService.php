<?php

declare(strict_types=1);

namespace App\Services\Geo;

use App\Models\Country;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class CountryService
{
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return Country::query()
            ->withCount('cities')
            ->orderBy('name_en')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Country
    {
        return Country::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Country $country, array $data): Country
    {
        $country->update($data);

        return $country->refresh();
    }

    public function delete(Country $country): void
    {
        $country->delete();
    }
}
