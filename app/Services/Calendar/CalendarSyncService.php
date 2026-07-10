<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Place;
use App\Models\PlaceBlocking;
use App\Models\PlaceCalendarFeed;
use App\Services\Place\PlaceAvailabilityService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Host-facing calendar-sync management, shared by the web Availability page
 * and the mobile API: the export URL (+ its secret token lifecycle) and the
 * place's list of imported feeds.
 */
final class CalendarSyncService
{
    public function __construct(
        private readonly CalendarImportService $import,
        private readonly PlaceAvailabilityService $availability,
    ) {}

    /**
     * Everything the web Availability page renders, in one call: the upcoming
     * blockings (feeds loaded so imported rows can show "via {feed}") plus the
     * calendar-sync card data (export URL + feed list).
     *
     * @return array{blockings: Collection<int, PlaceBlocking>, export_url: string, feeds: Collection<int, PlaceCalendarFeed>}
     */
    public function availabilityPageData(Place $place): array
    {
        $blockings = $this->availability->upcomingBlockingsForHost($place);
        $blockings->load('feed');

        return ['blockings' => $blockings, ...$this->overview($place)];
    }

    /**
     * Everything the sync screen shows: the copyable export URL (token minted
     * on first view) and the imported feeds with their last sync outcome.
     *
     * @return array{export_url: string, feeds: Collection<int, PlaceCalendarFeed>}
     */
    public function overview(Place $place): array
    {
        return [
            'export_url' => $this->exportUrl($place),
            'feeds' => $place->calendarFeeds()->orderBy('created_at')->get(),
        ];
    }

    public function exportUrl(Place $place): string
    {
        return route('calendar.export', [
            'place' => $place->id,
            'token' => $place->ensureCalendarToken(),
        ]);
    }

    /**
     * Add an external feed and sync it immediately so its dates block right
     * away (the host is looking at the calendar as they paste the link).
     *
     * @param  array{name: string, url: string}  $data
     */
    public function addFeed(Place $place, array $data): PlaceCalendarFeed
    {
        $max = (int) config('calendar.max_feeds_per_place');
        if ($place->calendarFeeds()->count() >= $max) {
            throw ValidationException::withMessages([
                'url' => __('A place can import at most :max calendars.', ['max' => $max]),
            ]);
        }

        $feed = $place->calendarFeeds()->create($data);
        $this->import->syncFeed($feed);

        return $feed->refresh();
    }

    /** Remove a feed and free every date it was blocking. */
    public function removeFeed(PlaceCalendarFeed $feed): void
    {
        // Explicit (not just the FK cascade) so behavior is DB-agnostic.
        $feed->blockings()->delete();
        $feed->delete();
    }

    /** Re-fetch all of this place's feeds right now ("Sync now"). */
    public function syncNow(Place $place): void
    {
        $this->import->syncPlace($place);
    }

    /**
     * Mint a fresh export token — the old URL dies instantly. For when a host
     * pasted the link somewhere they regret.
     */
    public function rotateToken(Place $place): string
    {
        $place->forceFill(['calendar_token' => Str::lower(Str::random(40))])->save();

        return $this->exportUrl($place);
    }
}
