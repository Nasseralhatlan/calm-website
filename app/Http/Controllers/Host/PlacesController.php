<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Enums\PlaceReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Host\SaveDraftRequest;
use App\Http\Requests\Host\StorePlaceRequest;
use App\Models\AttributeGroup;
use App\Models\City;
use App\Models\Place;
use App\Models\PlaceType;
use App\Services\Place\PlaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlacesController extends Controller
{
    public function __construct(private readonly PlaceService $service) {}

    /**
     * "Become a host" — the multi-step wizard. Everything the front-end needs
     * (place types, city areas, attribute catalog) is loaded here and handed
     * to Alpine via a JSON script tag in the view.
     */
    public function create(Request $request): View
    {
        // Cities grouped with their areas — the wizard renders a 2-stage picker
        // (city card with emoji → list of that city's areas).
        $cities = City::with(['areas' => fn ($q) => $q->orderBy('name_en')])
            ->orderBy('name_en')
            ->get(['id', 'name_ar', 'name_en', 'avatar']);

        // Resume an in-progress draft when `?draft={id}` is on the URL. Only
        // the host who owns the row + only while it's still a Draft.
        $draft = null;
        if ($draftId = $request->integer('draft')) {
            $draft = Place::query()
                ->with('cityArea:id,city_id')
                ->where('id', $draftId)
                ->where('host_user_id', $request->user()->id)
                ->where('review_status', PlaceReviewStatus::Draft->value)
                ->first();
        }

        return view('host.places.create', [
            'placeTypes' => PlaceType::orderBy('name_en')->get(['id', 'name_ar', 'name_en', 'icon']),
            'cities' => $cities,
            'attributeGroups' => AttributeGroup::with(['attributes' => fn ($q) => $q->orderBy('name_en')])
                ->orderBy('name_en')
                ->get(),
            'draft' => $draft,
        ]);
    }

    /**
     * Wizard auto-save. Called every time the host advances a step; upserts
     * the host's in-progress draft and returns its id so the client can keep
     * patching the same record.
     */
    public function saveDraft(SaveDraftRequest $request): JsonResponse
    {
        $draftId = $request->integer('draft_id') ?: null;

        $draft = $this->service->saveDraftForHost(
            $request->user(),
            $request->placeData(),
            $draftId,
        );

        return response()->json([
            'id' => $draft->id,
            'review_status' => $draft->review_status->value,
        ]);
    }

    public function store(StorePlaceRequest $request): RedirectResponse
    {
        $place = $this->service->createForHost(
            $request->user(),
            $request->placeData(),
            $request->integer('draft_id') ?: null,
        );

        return redirect()
            ->route('user.places')
            ->with('status', __('Place ":title" created — pending review.', ['title' => $place->title]));
    }

    public function index(Request $request): View
    {
        return view('host.places.index', [
            'places' => $this->service->forHost($request->user()),
        ]);
    }
}
