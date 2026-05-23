<?php

namespace App\Http\Controllers;

use App\Models\Host;
use App\Models\HostAmenity;
use App\Models\HostFacility;
use App\Models\HostImage;

class AdminController extends Controller
{
    public function index(string $password)
    {
        abort_unless(
            hash_equals((string) config('admin.password'), $password),
            404
        );

        app()->setLocale('ar');

        $totals = [
            'hosts'      => Host::count(),
            'facilities' => HostFacility::count(),
            'amenities'  => HostAmenity::count(),
            'images'     => HostImage::count(),
        ];

        $byPlaceType = Host::query()
            ->selectRaw('place_type, count(*) as count')
            ->groupBy('place_type')
            ->pluck('count', 'place_type')
            ->all();

        $topFacilities = HostFacility::query()
            ->selectRaw('`key`, count(*) as count')
            ->groupBy('key')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'key')
            ->all();

        $topAmenities = HostAmenity::query()
            ->selectRaw('`key`, count(*) as count')
            ->groupBy('key')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'key')
            ->all();

        $recentHosts = Host::query()
            ->withCount(['facilities', 'amenities', 'images'])
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        return view('admin.index', [
            'totals'        => $totals,
            'byPlaceType'   => $byPlaceType,
            'topFacilities' => $topFacilities,
            'topAmenities'  => $topAmenities,
            'recentHosts'   => $recentHosts,
        ]);
    }
}
