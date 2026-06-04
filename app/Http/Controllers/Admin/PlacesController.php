<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePlaceRequest;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Services\Place\PlaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlacesController extends Controller
{
    public function __construct(private readonly PlaceService $service) {}

    public function index(): View
    {
        return view('admin.places.index', ['places' => $this->service->paginate()]);
    }

    public function edit(Place $place): View
    {
        $place->load(['host', 'type', 'cityArea.city', 'photos', 'attributeValues.attribute']);

        return view('admin.places.edit', [
            'place' => $place,
            'placeTypes' => PlaceType::orderBy('name_en')->get(),
            'cityAreas' => CityArea::with('city')->orderBy('name_en')->get(),
        ]);
    }

    public function update(UpdatePlaceRequest $request, Place $place): RedirectResponse
    {
        $this->service->update($place, $request->validated());

        return redirect()
            ->route('admin.places.index')
            ->with('status', __('Place ":title" updated.', ['title' => $place->title]));
    }

    public function destroy(Place $place): RedirectResponse
    {
        $title = $place->title;
        $this->service->delete($place);

        return redirect()
            ->route('admin.places.index')
            ->with('status', __('Place ":title" deleted.', ['title' => $title]));
    }
}
