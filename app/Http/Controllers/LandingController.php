<?php

namespace App\Http\Controllers;

use App\Models\PlaceList;
use App\Services\Place\PlaceListService;
use App\Services\Place\PlaceService;
use App\Services\Web\WebHomeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function __construct(private readonly WebHomeService $home) {}

    /**
     * The browse home — app-style: pill search bar, quick place-type boxes,
     * curated lists, and an inline search-results grid. Replaces the old
     * marketing landing (its content lives on at /about).
     */
    public function index(): View
    {
        return view('home', $this->home->data(auth('api')->user()));
    }

    /** «عرض الكل» — one curated list's places on their own page. */
    public function list(PlaceList $placeList, PlaceListService $lists, PlaceService $places): View
    {
        $list = $lists->findForWeb($placeList, $places, auth('api')->user());

        abort_if($list === null, 404);

        return view('list', ['list' => $list]);
    }

    public function switchLocale(string $locale): RedirectResponse
    {
        if (! \in_array($locale, ['en', 'ar'], true)) {
            $locale = 'en';
        }

        return back()->withCookie(cookie()->forever('locale', $locale));
    }
}
