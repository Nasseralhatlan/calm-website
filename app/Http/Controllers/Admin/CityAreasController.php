<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCityAreaRequest;
use App\Http\Requests\Admin\UpdateCityAreaRequest;
use App\Models\City;
use App\Models\CityArea;
use App\Services\Geo\CityAreaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CityAreasController extends Controller
{
    public function __construct(private readonly CityAreaService $service) {}

    public function index(): View
    {
        return view('admin.city-areas.index', ['cityAreas' => $this->service->paginate()]);
    }

    public function create(): View
    {
        return view('admin.city-areas.create', [
            'cityArea' => new CityArea,
            'cities' => City::with('country')->orderBy('name_en')->get(),
        ]);
    }

    public function store(StoreCityAreaRequest $request): RedirectResponse
    {
        $area = $this->service->create($request->validated());

        return redirect()
            ->route('admin.city-areas.index')
            ->with('status', __('Area ":name" created.', ['name' => $area->name_en]));
    }

    public function edit(CityArea $cityArea): View
    {
        return view('admin.city-areas.edit', [
            'cityArea' => $cityArea,
            'cities' => City::with('country')->orderBy('name_en')->get(),
        ]);
    }

    public function update(UpdateCityAreaRequest $request, CityArea $cityArea): RedirectResponse
    {
        $this->service->update($cityArea, $request->validated());

        return redirect()
            ->route('admin.city-areas.index')
            ->with('status', __('Area ":name" updated.', ['name' => $cityArea->name_en]));
    }

    public function destroy(CityArea $cityArea): RedirectResponse
    {
        $name = $cityArea->name_en;
        $this->service->delete($cityArea);

        return redirect()
            ->route('admin.city-areas.index')
            ->with('status', __('Area ":name" deleted.', ['name' => $name]));
    }
}
