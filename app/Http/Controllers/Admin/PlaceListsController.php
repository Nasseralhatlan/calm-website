<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlaceListRequest;
use App\Http\Requests\Admin\UpdatePlaceListRequest;
use App\Models\Place;
use App\Models\PlaceList;
use App\Services\Place\PlaceListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin curation surface for landing-page sections. Each PlaceList becomes
 * a section row on the home page (rendered by LandingController).
 */
class PlaceListsController extends Controller
{
    public function __construct(private readonly PlaceListService $service) {}

    public function index(): View
    {
        return view('admin.place-lists.index', ['lists' => $this->service->paginate()]);
    }

    public function create(): View
    {
        return view('admin.place-lists.create', ['list' => new PlaceList]);
    }

    public function store(StorePlaceListRequest $request): RedirectResponse
    {
        $list = $this->service->create($request->validated());

        return redirect()
            ->route('admin.place-lists.edit', $list)
            ->with('status', __('List ":name" created — now add some places.', ['name' => $list->name_en]));
    }

    public function edit(PlaceList $placeList): View
    {
        $placeList->load(['places' => fn ($q) => $q->with('type', 'cityArea.city', 'coverPhoto')]);

        return view('admin.place-lists.edit', ['list' => $placeList]);
    }

    public function update(UpdatePlaceListRequest $request, PlaceList $placeList): RedirectResponse
    {
        $this->service->update($placeList, $request->validated());

        return redirect()
            ->route('admin.place-lists.edit', $placeList)
            ->with('status', __('List ":name" updated.', ['name' => $placeList->name_en]));
    }

    public function destroy(PlaceList $placeList): RedirectResponse
    {
        $name = $placeList->name_en;
        $this->service->delete($placeList);

        return redirect()
            ->route('admin.place-lists.index')
            ->with('status', __('List ":name" deleted.', ['name' => $name]));
    }

    /**
     * Detach a place from the list (admin remove-place button on list edit).
     * Adding places lives on the place-edit page now — see
     * `app/Http/Controllers/Admin/PlacesController.php::edit()`.
     */
    public function detach(PlaceList $placeList, Place $place): RedirectResponse
    {
        $this->service->removePlace($placeList, $place);

        return back()->with('status', __('Place removed from list.'));
    }
}
