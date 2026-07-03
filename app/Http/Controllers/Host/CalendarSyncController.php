<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Controller;
use App\Http\Requests\Host\StoreCalendarFeedRequest;
use App\Models\Place;
use App\Models\PlaceCalendarFeed;
use App\Services\Calendar\CalendarSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web management of a place's calendar sync (the "Calendar sync" card on the
 * Availability page): add/remove imported feeds, sync now, rotate the export
 * link. Owner-only (or admin acting on the host's behalf).
 */
class CalendarSyncController extends Controller
{
    public function __construct(private readonly CalendarSyncService $sync) {}

    /** Add an external iCal URL and sync it immediately. */
    public function store(StoreCalendarFeedRequest $request, Place $place): RedirectResponse
    {
        $this->authorizeOwner($request, $place);

        $feed = $this->sync->addFeed($place, $request->feedData());

        return redirect()
            ->route('host.places.availability', $place)
            ->with('status', __('Calendar ":name" connected.', ['name' => $feed->name]));
    }

    /** Remove a feed — its imported blocks are freed immediately. */
    public function destroy(Request $request, Place $place, PlaceCalendarFeed $calendarFeed): RedirectResponse
    {
        $this->authorizeOwner($request, $place);

        $name = $calendarFeed->name;
        $this->sync->removeFeed($calendarFeed);

        return redirect()
            ->route('host.places.availability', $place)
            ->with('status', __('Calendar ":name" removed — its blocked dates are free again.', ['name' => $name]));
    }

    /** Re-fetch all of this place's feeds right now. */
    public function syncNow(Request $request, Place $place): RedirectResponse
    {
        $this->authorizeOwner($request, $place);

        $this->sync->syncNow($place);

        return redirect()
            ->route('host.places.availability', $place)
            ->with('status', __('Calendars synced.'));
    }

    /** Regenerate the export link (the old URL stops working instantly). */
    public function rotateToken(Request $request, Place $place): RedirectResponse
    {
        $this->authorizeOwner($request, $place);

        $this->sync->rotateToken($place);

        return redirect()
            ->route('host.places.availability', $place)
            ->with('status', __('Export link regenerated — update it on the other platforms.'));
    }

    /** Only the place's own host (or an admin acting for them) may manage sync. */
    private function authorizeOwner(Request $request, Place $place): void
    {
        $user = $request->user();

        abort_unless(
            $user !== null && ($place->host_user_id === $user->id || $user->isAdmin()),
            Response::HTTP_FORBIDDEN,
        );
    }
}
