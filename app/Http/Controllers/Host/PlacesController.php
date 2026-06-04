<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Controller;
use App\Http\Requests\Host\StorePlaceRequest;
use App\Models\CityArea;
use App\Models\PlaceType;
use App\Services\Place\PlaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlacesController extends Controller
{
    public function __construct(private readonly PlaceService $service) {}

    /**
     * The "become a host" landing page — a form to register the first place.
     */
    public function create(): View
    {
        return view('host.places.create', [
            'placeTypes' => PlaceType::orderBy('name_en')->get(),
            'cityAreas' => CityArea::with('city')->orderBy('name_en')->get(),
        ]);
    }

    public function store(StorePlaceRequest $request): RedirectResponse
    {
        $place = $this->service->createForHost($request->user(), $request->validated());

        return redirect()
            ->route('user.places')
            ->with('status', __('Place ":title" created — pending review.', ['title' => $place->title]));
    }

    /**
     * The host's own list of places.
     */
    public function index(Request $request): View
    {
        return view('host.places.index', [
            'places' => $this->service->forHost($request->user()),
        ]);
    }
}
