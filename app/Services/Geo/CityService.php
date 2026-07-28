<?php

declare(strict_types=1);

namespace App\Services\Geo;

use App\Models\City;
use App\Models\Place;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class CityService
{
    public function paginate(?int $perPage = null): LengthAwarePaginator
    {
        return City::query()
            ->with('country')
            ->withCount('areas')
            ->orderBy('name_en')
            ->paginate($perPage ?? config('pagination.per_page'));
    }

    /**
     * Active cities for the mobile API, each with its areas so the app can
     * render the city → area picker in a single call. Deliberately NOT
     * filtered to cities that already have listings: this endpoint is also
     * the host wizard's reference data, and a new marketplace (or new city)
     * must be pickable BEFORE its first place exists — otherwise no host
     * could ever create one. A guest picking an empty city just gets an
     * empty search result.
     *
     * @return Collection<int, City>
     */
    public function activeForApi(): Collection
    {
        return City::query()
            ->active()
            ->with(['areas' => fn ($q) => $q->orderBy('name_en')])
            ->orderBy('name_en')
            ->get();
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

    /**
     * Deleting a city cascades into its areas, and places.city_area_id is
     * ON DELETE RESTRICT — so a city with any place under it would 500 with
     * a raw FK error. Refuse with a readable one instead.
     */
    public function delete(City $city): void
    {
        $placesCount = Place::query()
            ->whereHas('cityArea', fn ($q) => $q->where('city_id', $city->id))
            ->count();

        if ($placesCount > 0) {
            throw ValidationException::withMessages([
                'city' => __('Cannot delete city ":name" — :count place(s) still use its areas. Move or delete those places first.', [
                    'name' => $city->name_en,
                    'count' => $placesCount,
                ]),
            ]);
        }

        $city->delete();
    }
}
