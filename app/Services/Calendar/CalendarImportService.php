<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Place;
use App\Models\PlaceBlocking;
use App\Models\PlaceCalendarFeed;
use App\Services\Notification\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Sabre\VObject\Component;
use Sabre\VObject\Reader;
use Throwable;

/**
 * Fetches the external iCal URLs hosts pasted into their places (Airbnb /
 * Gathern / Google) and mirrors their events into place_blockings rows with
 * source='ical'. Because availability everywhere already unions blockings +
 * bookings, imported dates block search/quote/calendar with no extra wiring.
 *
 * Reconciliation model: a feed is a full snapshot, not a diff. Each sync
 * upserts one blocking per VEVENT (keyed by the event UID) and deletes this
 * feed's blockings whose UID vanished — an external cancellation frees the
 * dates here on the next pass. Manual blocks are never touched, and a failed
 * fetch changes nothing (last-known state survives flaky feeds).
 */
final class CalendarImportService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /** Sync every feed of every place — the hourly scheduled sweep. */
    public function syncAll(): void
    {
        PlaceCalendarFeed::query()->chunkById(50, function ($feeds): void {
            foreach ($feeds as $feed) {
                $this->syncFeed($feed);
            }
        });
    }

    /** Sync all feeds of one place — the "Sync now" button. */
    public function syncPlace(Place $place): void
    {
        foreach ($place->calendarFeeds()->get() as $feed) {
            $this->syncFeed($feed);
        }
    }

    public function syncFeed(PlaceCalendarFeed $feed): void
    {
        try {
            $events = $this->fetchEvents($feed->url);
        } catch (Throwable $e) {
            // Leave existing imported blocks intact — a transient outage on
            // Airbnb's side must not wipe the host's availability.
            Log::warning('calendar-sync: feed fetch failed', [
                'feed_id' => $feed->id,
                'place_id' => $feed->place_id,
                'error' => $e->getMessage(),
            ]);

            $feed->update([
                'last_status' => 'error',
                'last_error' => Str::limit($e->getMessage(), 480),
            ]);

            return;
        }

        $this->reconcile($feed, $events);

        $feed->update([
            'last_synced_at' => now(),
            'last_status' => 'ok',
            'last_error' => null,
        ]);
    }

    /**
     * Download + parse a feed into busy ranges keyed by event UID.
     *
     * @return array<string, array{start: string, end: string, summary: ?string}>
     */
    private function fetchEvents(string $url): array
    {
        // SSRF guard: only plain web URLs, short timeout, small body. The URL
        // is host-supplied, so treat it as hostile input.
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('Calendar URL must start with http or https.');
        }

        $response = Http::timeout((int) config('calendar.fetch_timeout'))
            ->withOptions(['allow_redirects' => ['max' => 3]])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Calendar URL returned HTTP '.$response->status().'.');
        }

        $body = $response->body();
        if (strlen($body) > (int) config('calendar.max_body_bytes')) {
            throw new RuntimeException('Calendar file is too large.');
        }

        $calendar = Reader::read($body, Reader::OPTION_FORGIVING);

        $today = CarbonImmutable::today();
        $events = [];

        /** @var Component $vevent */
        foreach ($calendar->select('VEVENT') as $vevent) {
            if (! isset($vevent->DTSTART)) {
                continue;
            }

            $start = CarbonImmutable::instance($vevent->DTSTART->getDateTime())->startOfDay();

            // iCal DTEND is exclusive; our end_date is the last occupied night
            // (inclusive). (DTEND − 1 second)'s date handles both all-day
            // (DTEND 07-12 → night of 07-11) and timed events uniformly,
            // over-blocking rather than under-blocking on odd timed feeds.
            // Missing DTEND = single-day event.
            $end = isset($vevent->DTEND)
                ? CarbonImmutable::instance($vevent->DTEND->getDateTime())->subSecond()->startOfDay()
                : $start;
            if ($end->lessThan($start)) {
                $end = $start;
            }

            // Fully-past ranges never affect availability — skip them.
            if ($end->lessThan($today)) {
                continue;
            }

            // Airbnb/Gathern send stable UIDs per reservation. Some calendars
            // omit UID — derive a stable one from the event's content instead.
            $uid = isset($vevent->UID) && (string) $vevent->UID !== ''
                ? (string) $vevent->UID
                : 'derived-'.sha1($start->toDateString().'|'.$end->toDateString().'|'.(string) ($vevent->SUMMARY ?? ''));

            $summary = isset($vevent->SUMMARY) ? Str::limit((string) $vevent->SUMMARY, 250) : null;

            $events[Str::limit($uid, 250, '')] = [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'summary' => $summary ?: null,
            ];
        }

        return $events;
    }

    /**
     * Make this feed's blockings exactly mirror the fetched snapshot: upsert
     * by (feed, external_uid), then drop rows whose UID left the feed.
     *
     * @param  array<string, array{start: string, end: string, summary: ?string}>  $events
     */
    private function reconcile(PlaceCalendarFeed $feed, array $events): void
    {
        $now = now();
        // Snapshot the UIDs we already mirror BEFORE the upsert, so we can
        // tell genuinely new external events apart from re-synced ones (a
        // conflict is alerted once, not on every hourly pass).
        $knownUids = $feed->blockings()->pluck('external_uid')->all();
        // PlaceBlocking uses HasUuids — bulk upsert() bypasses the `creating`
        // event, so mint ids ourselves (same trick as PlaceService::syncAttributes).
        $stub = new PlaceBlocking;

        $rows = [];
        foreach ($events as $uid => $event) {
            $rows[] = [
                'id' => $stub->newUniqueId(),
                'place_id' => $feed->place_id,
                'calendar_feed_id' => $feed->id,
                'external_uid' => $uid,
                'source' => 'ical',
                'start_date' => $event['start'],
                'end_date' => $event['end'],
                'reason' => $event['summary'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($feed, $events, $rows): void {
            // Events gone from the feed = cancelled externally → free the dates.
            $feed->blockings()
                ->whereNotIn('external_uid', array_keys($events))
                ->delete();

            if ($rows !== []) {
                PlaceBlocking::upsert(
                    $rows,
                    ['calendar_feed_id', 'external_uid'],
                    ['start_date', 'end_date', 'reason', 'updated_at'],
                );
            }
        });

        $this->alertBookingConflicts($feed, array_diff_key($events, array_flip($knownUids)));
    }

    /**
     * A newly-imported external event landing on dates an active Calm booking
     * holds means the place is double-booked across platforms. The import
     * itself stays (the dates really are taken externally) — but the host is
     * told immediately instead of finding two families at the door.
     *
     * @param  array<string, array{start: string, end: string, summary: ?string}>  $newEvents
     */
    private function alertBookingConflicts(PlaceCalendarFeed $feed, array $newEvents): void
    {
        if ($newEvents === []) {
            return;
        }

        try {
            foreach ($newEvents as $event) {
                $conflicting = $feed->place->bookings()
                    ->activeHold()
                    ->where('start_date', '<=', $event['end'])
                    ->where('end_date', '>=', $event['start'])
                    ->get();

                foreach ($conflicting as $booking) {
                    $this->notifications->calendarConflict($booking);
                }
            }
        } catch (Throwable $e) {
            // Alerting is best-effort — never let it break the sync itself.
            Log::warning('calendar-sync: conflict alert failed', [
                'feed_id' => $feed->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
