<?php

declare(strict_types=1);

namespace App\Services\Geo;

use App\Models\Country;
use App\Models\Place;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class CountryService
{
    public function paginate(?int $perPage = null): LengthAwarePaginator
    {
        return Country::query()
            ->withCount('cities')
            ->orderBy('name_en')
            ->paginate($perPage ?? config('pagination.per_page'));
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

    /**
     * Country delete cascades to cities, then to areas — and places restrict
     * area deletes. Refuse with a readable error instead of a raw FK 500.
     */
    public function delete(Country $country): void
    {
        $placesCount = Place::query()
            ->whereHas('cityArea.city', fn ($q) => $q->where('country_id', $country->id))
            ->count();

        if ($placesCount > 0) {
            throw ValidationException::withMessages([
                'country' => __('Cannot delete country ":name" — :count place(s) still exist in its cities. Move or delete those places first.', [
                    'name' => $country->name_en,
                    'count' => $placesCount,
                ]),
            ]);
        }

        $country->delete();
    }
}
