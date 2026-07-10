<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Place;
use Carbon\CarbonImmutable;
use Sabre\VObject\Component\VCalendar;

/**
 * Builds a place's public iCal (.ics) feed — the file Airbnb / Gathern /
 * Google poll after the host pastes our export URL into them. One all-day
 * VEVENT per Calm-native busy range: active bookings + the host's manual
 * blocks. Imported (source='ical') blocks are EXCLUDED so we never echo a
 * platform's own dates back to it (which could ping-pong forever).
 *
 * Date semantics: our end_date is the last occupied night (inclusive); iCal
 * DTEND is exclusive (the checkout day) → DTEND = end_date + 1 day.
 */
final class CalendarExportService
{
    public function feed(Place $place): string
    {
        $calendar = new VCalendar;
        $calendar->PRODID = '-//Calm//Host Availability//EN';
        $calendar->add('METHOD', 'PUBLISH');
        $calendar->add('X-WR-CALNAME', 'Calm — '.($place->title ?? $place->id));

        $today = CarbonImmutable::today()->toDateString();

        // Guest bookings that still hold dates (confirmed/completed + live
        // pending holds) — only ranges that aren't fully in the past.
        $bookings = $place->bookings()
            ->activeHold()
            ->where('end_date', '>=', $today)
            ->orderBy('start_date')
            ->get(['id', 'start_date', 'end_date']);

        foreach ($bookings as $booking) {
            $this->addBusyEvent(
                $calendar,
                "booking-{$booking->id}@calm",
                'Reserved', // deliberately no guest PII — the URL is a shared secret
                CarbonImmutable::parse($booking->start_date->toDateString()),
                CarbonImmutable::parse($booking->end_date->toDateString()),
            );
        }

        // The host's own manual blocks. source='ical' rows are skipped — they
        // belong to another platform's calendar, not ours.
        $blockings = $place->blockings()
            ->where('source', 'manual')
            ->where('end_date', '>=', $today)
            ->orderBy('start_date')
            ->get(['id', 'start_date', 'end_date']);

        foreach ($blockings as $blocking) {
            $this->addBusyEvent(
                $calendar,
                "block-{$blocking->id}@calm",
                'Blocked',
                CarbonImmutable::parse($blocking->start_date->toDateString()),
                CarbonImmutable::parse($blocking->end_date->toDateString()),
            );
        }

        return $calendar->serialize();
    }

    /** One all-day busy range; $endInclusive is our last-occupied-night date. */
    private function addBusyEvent(
        VCalendar $calendar,
        string $uid,
        string $summary,
        CarbonImmutable $start,
        CarbonImmutable $endInclusive,
    ): void {
        $event = $calendar->add('VEVENT', [
            'UID' => $uid,
            'SUMMARY' => $summary,
        ]);

        // VALUE=DATE makes these all-day events (date only, no time/zone).
        $event->add('DTSTART', $start, ['VALUE' => 'DATE']);
        $event->add('DTEND', $endInclusive->addDay(), ['VALUE' => 'DATE']);
    }
}
