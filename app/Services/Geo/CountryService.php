<?php

declare(strict_types=1);

namespace App\Services\Geo;

use App\Models\Country;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

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
     * Active countries for the mobile API — used by the country picker in
     * the login flow (dial-code dropdown) and any country-filter chips.
     *
     * @return Collection<int, Country>
     */
    public function activeForApi(): Collection
    {
        return Country::query()->active()->orderBy('name_en')->get();
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
