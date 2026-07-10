<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Host\StoreCalendarFeedRequest;
use App\Http\Resources\Api\PlaceCalendarFeedResource;
use App\Http\Responses\ApiResponse;
use App\Models\Place;
use App\Models\PlaceCalendarFeed;
use App\Services\Calendar\CalendarSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobile host calendar sync (Airbnb / Gathern style): the place's secret .ics
 * export URL to paste into other platforms, and the external feed URLs pasted
 * into Calm. Owner-only — same guard as HostBlockingController. Everything is
 * per place, matching how each Airbnb/Gathern listing has its own calendar.
 */
class HostCalendarSyncController extends Controller
{
    public function __construct(private readonly CalendarSyncService $sync) {}

    /** The sync screen: export URL (token minted on first view) + feeds. */
    public function show(Request $request, Place $place): JsonResponse
    {
        $this->authorizeOwner($request, $place);

        $overview = $this->sync->overview($place);

        return ApiResponse::success(
            data: [
                'place_id' => $place->id,
                'export_url' => $overview['export_url'],
                'feeds' => PlaceCalendarFeedResource::collection($overview['feeds'])->resolve($request),
            ],
            message: 'Calendar sync fetched.',
        );
    }

    /** Connect an external iCal URL — synced immediately so dates block now. */
    public function storeFeed(StoreCalendarFeedRequest $request, Place $place): JsonResponse
    {
        $this->authorizeOwner($request, $place);

        $feed = $this->sync->addFeed($place, $request->feedData());

        return ApiResponse::success(
            data: (new PlaceCalendarFeedResource($feed))->resolve($request),
            message: 'Calendar connected.',
            status: Response::HTTP_CREATED,
        );
    }

    /** Disconnect a feed — every date it was blocking frees immediately. */
    public function destroyFeed(Request $request, Place $place, PlaceCalendarFeed $calendarFeed): JsonResponse
    {
        $this->authorizeOwner($request, $place);
        // Defence-in-depth alongside the route's scoped binding.
        abort_unless($calendarFeed->place_id === $place->id, Response::HTTP_NOT_FOUND);

        $this->sync->removeFeed($calendarFeed);

        return ApiResponse::success(message: 'Calendar removed — its blocked dates are free again.');
    }

    /** Re-fetch all of this place's feeds right now ("Sync now"). */
    public function syncNow(Request $request, Place $place): JsonResponse
    {
        $this->authorizeOwner($request, $place);

        $this->sync->syncNow($place);

        return ApiResponse::success(
            data: [
                'feeds' => PlaceCalendarFeedResource::collection(
                    $place->calendarFeeds()->orderBy('created_at')->get(),
                )->resolve($request),
            ],
            message: 'Calendars synced.',
        );
    }

    /** Regenerate the export link — the old URL stops working instantly. */
    public function rotateToken(Request $request, Place $place): JsonResponse
    {
        $this->authorizeOwner($request, $place);

        $exportUrl = $this->sync->rotateToken($place);

        return ApiResponse::success(
            data: ['export_url' => $exportUrl],
            message: 'Export link regenerated — update it on the other platforms.',
        );
    }

    /** Only the place's own host may manage its calendar sync. */
    private function authorizeOwner(Request $request, Place $place): void
    {
        abort_unless($place->host_user_id === $request->user()?->id, Response::HTTP_FORBIDDEN);
    }
}
