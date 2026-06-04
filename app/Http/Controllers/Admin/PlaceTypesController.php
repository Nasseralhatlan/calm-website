<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlaceTypeRequest;
use App\Http\Requests\Admin\UpdatePlaceTypeRequest;
use App\Models\PlaceType;
use App\Services\Place\PlaceTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlaceTypesController extends Controller
{
    public function __construct(private readonly PlaceTypeService $service) {}

    public function index(): View
    {
        return view('admin.place-types.index', ['placeTypes' => $this->service->paginate()]);
    }

    public function create(): View
    {
        return view('admin.place-types.create', ['placeType' => new PlaceType]);
    }

    public function store(StorePlaceTypeRequest $request): RedirectResponse
    {
        $placeType = $this->service->create($request->validated());

        return redirect()
            ->route('admin.place-types.index')
            ->with('status', __('Place type ":name" created.', ['name' => $placeType->name_en]));
    }

    public function edit(PlaceType $placeType): View
    {
        return view('admin.place-types.edit', ['placeType' => $placeType]);
    }

    public function update(UpdatePlaceTypeRequest $request, PlaceType $placeType): RedirectResponse
    {
        $this->service->update($placeType, $request->validated());

        return redirect()
            ->route('admin.place-types.index')
            ->with('status', __('Place type ":name" updated.', ['name' => $placeType->name_en]));
    }

    public function destroy(PlaceType $placeType): RedirectResponse
    {
        $name = $placeType->name_en;
        $this->service->delete($placeType);

        return redirect()
            ->route('admin.place-types.index')
            ->with('status', __('Place type ":name" deleted.', ['name' => $name]));
    }
}
