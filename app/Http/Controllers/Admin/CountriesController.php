<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCountryRequest;
use App\Http\Requests\Admin\UpdateCountryRequest;
use App\Models\Country;
use App\Services\Geo\CountryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CountriesController extends Controller
{
    public function __construct(private readonly CountryService $service) {}

    public function index(): View
    {
        return view('admin.countries.index', ['countries' => $this->service->paginate()]);
    }

    public function create(): View
    {
        return view('admin.countries.create', ['country' => new Country]);
    }

    public function store(StoreCountryRequest $request): RedirectResponse
    {
        $country = $this->service->create($request->validated());

        return redirect()
            ->route('admin.countries.index')
            ->with('status', __('Country ":name" created.', ['name' => $country->name_en]));
    }

    public function edit(Country $country): View
    {
        return view('admin.countries.edit', compact('country'));
    }

    public function update(UpdateCountryRequest $request, Country $country): RedirectResponse
    {
        $this->service->update($country, $request->validated());

        return redirect()
            ->route('admin.countries.index')
            ->with('status', __('Country ":name" updated.', ['name' => $country->name_en]));
    }

    public function destroy(Country $country): RedirectResponse
    {
        $name = $country->name_en;
        $this->service->delete($country);

        return redirect()
            ->route('admin.countries.index')
            ->with('status', __('Country ":name" deleted.', ['name' => $name]));
    }
}
