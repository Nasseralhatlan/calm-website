<?php

declare(strict_types=1);

namespace App\Services\Geo;

use App\Models\City;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

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
     * Active cities for the mobile API home screen, each with its areas so the
     * app can render the city → area picker in a single call. Only cities that
     * actually have at least one visible (active + approved) place are returned,
     * and each city's areas are likewise trimmed to those with a visible place —
     * so the picker never offers a city/area you can't book in.
     *
     * @return Collection<int, City>
     */
    public function activeForApi(): Collection
    {
        return City::query()
            ->active()
            ->whereHas('areas.places', fn ($q) => $q->visible())
            ->with(['areas' => fn ($q) => $q
                ->whereHas('places', fn ($p) => $p->visible())
                ->orderBy('name_en')])
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

    public function delete(City $city): void
    {
        $city->delete();
    }
}
