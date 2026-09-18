<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * The occasions wizard — web twin of the app's /occasions flow. The page is
 * public: browsing and filling it needs no account, only submitting does.
 * Everything it renders comes from config/occasions.php, which the app mirrors
 * in constants/occasions.ts.
 */
class OccasionsController extends Controller
{
    public function show(): View
    {
        return view('occasions.show');
    }
}
