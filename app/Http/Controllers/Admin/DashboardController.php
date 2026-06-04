<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\CityArea;
use App\Models\Country;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'counts' => [
                'countries' => Country::count(),
                'cities' => City::count(),
                'city_areas' => CityArea::count(),
                'users' => User::count(),
            ],
        ]);
    }
}
