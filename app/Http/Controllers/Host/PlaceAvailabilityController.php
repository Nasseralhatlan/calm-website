<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Controller;
use App\Http\Requests\Host\StoreBlockingRequest;
use App\Models\Place;
use App\Models\PlaceBlocking;
use App\Services\Calendar\CalendarSyncService;
use App\Services\Place\PlaceAvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Host-facing availability manager: the host blocks/unblocks date ranges for
 * one of their own places so those days show as unavailable in the mobile
 * calendar (see Api\PlaceAvailabilityController for the read side).
 */
class PlaceAvailabilityController extends Controller
{
    public function __construct(
        private readonly PlaceAvailabilityService $service,
        private readonly CalendarSyncService $calendarSync,
    ) {}

    /** Calendar + current blockings + calendar-sync card for one of the host's places. */
    public function show(Request $request, Place $place): View
    {
        $this->authorizeOwner($request, $place);

        $data = $this->calendarSync->availabilityPageData($place);

        return view('host.places.availability', [
            'place' => $place->load(['type', 'cityArea.city']),
            'blockings' => $data['blockings'],
            'exportUrl' => $data['export_url'],
            'feeds' => $data['feeds'],
        ]);
    }

    /** Block a date range. */
    public function store(StoreBlockingRequest $request, Place $place): RedirectResponse
    {
        $this->authorizeOwner($request, $place);

        $this->service->block($place, $request->blockingData());

        return redirect()
            ->route('host.places.availability', $place)
            ->with('status', __('Dates blocked successfully.'));
    }

    /** Lift a blocked range. */
    public function destroy(Request $request, Place $place, PlaceBlocking $blocking): RedirectResponse
    {
        $this->authorizeOwner($request, $place);
        // Defence-in-depth alongside the route's scoped binding: the blocking
        // must belong to this place, never another host's.
        abort_unless($blocking->place_id === $place->id, 404);

        $this->service->unblock($blocking);

        return redirect()
            ->route('host.places.availability', $place)
            ->with('status', __('Block removed — those dates are available again.'));
    }

    /**
     * Only the place's own host (or an admin acting on their behalf) may touch
     * its availability. 403 otherwise so one host can't manage another's dates.
     */
    private function authorizeOwner(Request $request, Place $place): void
    {
        $user = $request->user();

        abort_unless(
            $user !== null && ($place->host_user_id === $user->id || $user->isAdmin()),
            403,
        );
    }
}
