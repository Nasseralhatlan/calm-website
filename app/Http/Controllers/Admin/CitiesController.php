<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCityRequest;
use App\Http\Requests\Admin\UpdateCityRequest;
use App\Models\City;
use App\Models\Country;
use App\Services\Geo\CityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CitiesController extends Controller
{
    public function __construct(private readonly CityService $service) {}

    public function index(): View
    {
        return view('admin.cities.index', ['cities' => $this->service->paginate()]);
    }

    public function create(): View
    {
        return view('admin.cities.create', [
            'city' => new City,
            'countries' => Country::orderBy('name_en')->get(),
        ]);
    }

    public function store(StoreCityRequest $request): RedirectResponse
    {
        $city = $this->service->create($request->validated());

        return redirect()
            ->route('admin.cities.index')
            ->with('status', __('City ":name" created.', ['name' => $city->name_en]));
    }

    public function edit(City $city): View
    {
        return view('admin.cities.edit', [
            'city' => $city,
            'countries' => Country::orderBy('name_en')->get(),
        ]);
    }

    public function update(UpdateCityRequest $request, City $city): RedirectResponse
    {
        $this->service->update($city, $request->validated());

        return redirect()
            ->route('admin.cities.index')
            ->with('status', __('City ":name" updated.', ['name' => $city->name_en]));
    }

    public function destroy(City $city): RedirectResponse
    {
        $name = $city->name_en;
        $this->service->delete($city);

        return redirect()
            ->route('admin.cities.index')
            ->with('status', __('City ":name" deleted.', ['name' => $name]));
    }
}
