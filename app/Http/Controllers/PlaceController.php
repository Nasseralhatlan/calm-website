<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Place;
use Illuminate\View\View;

/**
 * Public-ish place view. Used both as the guest-facing listing page (when
 * approved + active) and as the in-review preview the admin sees from the
 * review screen. The view itself decides what's hidden vs shown based on
 * the `$preview` flag.
 */
class PlaceController extends Controller
{
    public function show(Place $place): View
    {
        $place->load([
            'host',
            'type',
            'cityArea.city',
            'photos',
            'attributeValues.attribute.group',
        ]);

        return view('places.show', [
            'place' => $place,
            'preview' => false,
        ]);
    }
}
