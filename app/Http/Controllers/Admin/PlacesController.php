<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePlaceRequest;
use App\Models\AttributeGroup;
use App\Models\City;
use App\Models\Place;
use App\Models\PlaceList;
use App\Models\PlaceType;
use App\Services\Place\PlaceService;
use App\Services\Place\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlacesController extends Controller
{
    public function __construct(
        private readonly PlaceService $service,
        private readonly SettingService $settings,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', '')) ?: null;
        $cityId = trim((string) $request->query('city', '')) ?: null;

        return view('admin.places.index', [
            ...$this->service->indexData($search, $cityId),
            'search' => $search,
            'cityId' => $cityId,
        ]);
    }

    /**
     * Admin edit via a pre-filled replica of the add wizard. Admins get every
     * host step plus an extra "Admin settings" step (featured lists + status +
     * review status + rejection reason). Saving keeps whatever status the admin
     * chose — no forced re-review.
     */
    public function edit(Place $place): View
    {
        $place->load(['cityArea:id,city_id', 'attributeValues', 'photos', 'lists']);

        $lists = PlaceList::query()->orderBy('sort_order')->orderBy('name_en')->get();
        $locale = app()->getLocale();

        return view('host.places.create', [
            ...$this->wizardCatalog(),
            'draft' => $place,
            'editConfig' => [
                'enabled' => true,
                'isAdmin' => true,
                'submitUrl' => route('admin.places.update', $place),
                'cancelUrl' => route('admin.places.index'),
                'lists' => $lists->map(fn (PlaceList $l): array => [
                    'id' => $l->id,
                    'label' => $locale === 'ar' ? $l->name_ar : $l->name_en,
                ])->all(),
                'selectedListIds' => $place->lists->pluck('id')->all(),
                'status' => $place->status->value,
                'reviewStatus' => $place->review_status->value,
                'rejectionReason' => $place->rejection_reason,
            ],
        ]);
    }

    public function update(UpdatePlaceRequest $request, Place $place): RedirectResponse
    {
        $this->service->updateByAdmin(
            $place,
            $request->placeData(),
            $request->attributesData(),
            $request->photosData(),
            $request->listsData(),
            $request->validated('host_phone'),
        );

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

    /**
     * Catalog data the shared wizard view needs (place types, cities+areas,
     * amenity groups, pricing rates). Mirrors the host controller's loader so
     * the admin edit screen is a true replica of the add page.
     *
     * @return array<string, mixed>
     */
    private function wizardCatalog(): array
    {
        $rate = $this->settings->byKeys(['commission_percentage'])['commission_percentage'] ?? null;

        return [
            'placeTypes' => PlaceType::active()->orderBy('name_en')->get(['id', 'name_ar', 'name_en', 'icon']),
            'cities' => City::query()
                ->active()
                ->with(['areas' => fn ($q) => $q->orderBy('name_en')])
                ->orderBy('name_en')
                ->get(['id', 'name_ar', 'name_en', 'avatar']),
            'attributeGroups' => AttributeGroup::query()
                ->with(['attributes' => fn ($q) => $q
                    ->withCount('placeValues')
                    ->orderByDesc('place_values_count')
                    ->orderBy('name_en')])
                ->orderBy('name_en')
                ->get(),
            'pricingRates' => ['commission' => is_numeric($rate) ? (float) $rate : 15.0],
        ];
    }
}
