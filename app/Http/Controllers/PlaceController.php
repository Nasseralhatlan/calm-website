<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\Place;
use Illuminate\View\View;

/**
 * Public-ish place view. Used both as the guest-facing listing page (when
 * approved + active) and as the in-review preview the admin/host sees from the
 * places tabs. The view itself decides what's hidden vs shown based on the
 * `$preview` / `$showStatusBanner` flags.
 */
class PlaceController extends Controller
{
    public function show(Place $place): View
    {
        // Optional viewer — the page is public, so resolve the JWT guard
        // directly rather than relying on `auth:api` middleware.
        $viewer = auth('api')->user();
        $isOwnerOrAdmin = $viewer !== null
            && ($viewer->id === $place->host_user_id || $viewer->isAdmin());

        // A place that isn't live (draft, pending, rejected, or inactive) is
        // visible ONLY to its owner or an admin — everyone else 404s so
        // work-in-progress never leaks via a direct URL.
        $isLive = $place->status === PlaceStatus::Active
            && $place->review_status === PlaceReviewStatus::Approved;
        abort_unless($isLive || $isOwnerOrAdmin, 404);

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
            // Owner/admin get a status banner pinned to the top (review +
            // active status) so they know what state the listing is in while
            // viewing it exactly as a guest would.
            'showStatusBanner' => $isOwnerOrAdmin,
            // Drives the banner's Edit / Back links to the right surface.
            'viewerIsAdmin' => $viewer?->isAdmin() ?? false,
        ]);
    }
}
