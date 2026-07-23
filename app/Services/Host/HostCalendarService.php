<?php

declare(strict_types=1);

namespace App\Services\Host;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\PlaceBlocking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read model behind the host bookings calendar. Merges everything that occupies
 * a host's dates — app bookings (Confirmed/Completed) + place blockings (manual
 * now, iCal-imported once calendar sync ships) — into a per-day view for the
 * mobile scrollable calendar, and a per-day detail list.
 *
 * Occupancy is [start_date, end_date] inclusive for both bookings and blockings,
 * matching PlaceAvailabilityService::expandBlockedDays.
 */
final class HostCalendarService
{
    /** Mirror PlaceAvailabilityService's window conventions. */
    private const DEFAULT_WINDOW_MONTHS = 12;

    private const MAX_WINDOW_MONTHS = 18;

    /** Bookings that actually occupy a calendar day (real, paid stays). */
    private const CALENDAR_STATUSES = [
        BookingStatus::Confirmed->value,
        BookingStatus::Completed->value,
    ];

    /** Eager-loads BookingResource needs on the host side. */
    private const BOOKING_WITH = ['place', 'place.coverPhoto', 'place.cityArea.city', 'place.type', 'guest', 'unit'];

    /**
     * Per-day summary across a date window for the scrollable calendar. Sparse —
     * only days that have something on them are returned.
     *
     * @return array{from: string, to: string, days: array<string, array{bookings: int, check_ins: int, check_outs: int, manual_block: bool, external_block: bool}>}
     */
    public function window(User $host, ?string $from, ?string $to, ?string $placeId): array
    {
        $start = $from !== null
            ? CarbonImmutable::parse($from)->startOfDay()
            : CarbonImmutable::now()->startOfDay();

        $end = $to !== null
            ? CarbonImmutable::parse($to)->startOfDay()
            : $start->addMonths(self::DEFAULT_WINDOW_MONTHS);

        // A `to` before `from` collapses to one day; cap the span either way.
        if ($end->lessThan($start)) {
            $end = $start;
        }
        $maxEnd = $start->addMonths(self::MAX_WINDOW_MONTHS);
        if ($end->greaterThan($maxEnd)) {
            $end = $maxEnd;
        }

        $days = [];
        $touch = function (string $date) use (&$days): void {
            $days[$date] ??= [
                'bookings' => 0, 'check_ins' => 0, 'check_outs' => 0,
                'manual_block' => false, 'external_block' => false,
            ];
        };

        // ── App bookings ──
        $bookings = Booking::query()
            ->where('host_user_id', $host->id)
            ->whereIn('booking_status', self::CALENDAR_STATUSES)
            ->when($placeId !== null, fn ($q) => $q->where('place_id', $placeId))
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get(['start_date', 'end_date']);

        foreach ($bookings as $booking) {
            $bStart = CarbonImmutable::parse($booking->start_date->toDateString());
            $bEnd = CarbonImmutable::parse($booking->end_date->toDateString());

            if ($bStart->betweenIncluded($start, $end)) {
                $touch($bStart->toDateString());
                $days[$bStart->toDateString()]['check_ins']++;
            }
            if ($bEnd->betweenIncluded($start, $end)) {
                $touch($bEnd->toDateString());
                $days[$bEnd->toDateString()]['check_outs']++;
            }

            foreach ($this->daysInWindow($bStart, $bEnd, $start, $end) as $date) {
                $touch($date);
                $days[$date]['bookings']++;
            }
        }

        // ── Place blockings (manual now, iCal-imported later) ──
        $blockings = PlaceBlocking::query()
            ->whereHas('place', fn ($q) => $q->where('host_user_id', $host->id))
            ->when($placeId !== null, fn ($q) => $q->where('place_id', $placeId))
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get();

        foreach ($blockings as $blocking) {
            // `source` column ships with calendar sync; treat absence as manual.
            $key = ($blocking->source ?? 'manual') === 'ical' ? 'external_block' : 'manual_block';
            $bStart = CarbonImmutable::parse($blocking->start_date->toDateString());
            $bEnd = CarbonImmutable::parse($blocking->end_date->toDateString());

            foreach ($this->daysInWindow($bStart, $bEnd, $start, $end) as $date) {
                $touch($date);
                $days[$date][$key] = true;
            }
        }

        ksort($days);

        return ['from' => $start->toDateString(), 'to' => $end->toDateString(), 'days' => $days];
    }

    /**
     * Everything on a single date: bookings occupying it + blockings covering it.
     *
     * @return array{bookings: Collection<int, Booking>, blockings: Collection<int, PlaceBlocking>}
     */
    public function day(User $host, string $date, ?string $placeId): array
    {
        $bookings = Booking::query()
            ->where('host_user_id', $host->id)
            ->whereIn('booking_status', self::CALENDAR_STATUSES)
            ->when($placeId !== null, fn ($q) => $q->where('place_id', $placeId))
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->with(self::BOOKING_WITH)
            ->orderBy('check_in_time')
            ->orderBy('id')
            ->get();

        $blockings = PlaceBlocking::query()
            ->whereHas('place', fn ($q) => $q->where('host_user_id', $host->id))
            ->when($placeId !== null, fn ($q) => $q->where('place_id', $placeId))
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderBy('start_date')
            ->get();

        return ['bookings' => $bookings, 'blockings' => $blockings];
    }

    /**
     * Y-m-d strings for each day of [rangeStart, rangeEnd] that falls inside the
     * [windowStart, windowEnd] window (both inclusive).
     *
     * @return list<string>
     */
    private function daysInWindow(
        CarbonImmutable $rangeStart,
        CarbonImmutable $rangeEnd,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
    ): array {
        $cursor = $rangeStart->lessThan($windowStart) ? $windowStart : $rangeStart;
        $last = $rangeEnd->greaterThan($windowEnd) ? $windowEnd : $rangeEnd;

        $dates = [];
        while ($cursor->lessThanOrEqualTo($last)) {
            $dates[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        return $dates;
    }
}
