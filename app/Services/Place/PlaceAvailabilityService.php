<?php

declare(strict_types=1);

namespace App\Services\Place;

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\Place;
use App\Models\PlaceBlocking;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class PlaceAvailabilityService
{
    /** Hard ceiling on the window so one call never expands more than this. */
    private const MAX_WINDOW_MONTHS = 18;

    /** Default look-ahead when the caller omits `to`. */
    private const DEFAULT_WINDOW_MONTHS = 12;

    /** Fallback rates used when the settings rows are missing or blank. */
    private const DEFAULT_COMMISSION_PERCENTAGE = 15.0;

    private const DEFAULT_VAT_PERCENTAGE = 15.0;

    public function __construct(private readonly SettingService $settings) {}

    /**
     * Every day a place is blocked (unavailable) inside the requested window,
     * sourced from its `place_blockings` ranges. Built for a mobile calendar:
     * returns both the flat `dates` list (mark each cell) and merged `ranges`
     * (shade contiguous blocks cheaply). Both derive from the same de-duplicated
     * day set, so they can never disagree.
     *
     * The window defaults to [today, today + 12 months] and is clamped to a
     * hard 18-month span — bounding the expansion regardless of how far out a
     * host has blocked. Blockings overlapping the window are clamped to it.
     *
     * Returns null when the place isn't publicly visible (draft, pending,
     * rejected, or inactive) so the controller can 404 cleanly — the same
     * visibility contract as the place-detail endpoint.
     *
     * @param  string|null  $from  Inclusive window start (Y-m-d); defaults to today.
     * @param  string|null  $to  Inclusive window end (Y-m-d); defaults to from + 12 months.
     * @return array{place_id: string, from: string, to: string, dates: list<string>, ranges: list<array{start_date: string, end_date: string}>}|null
     */
    public function unavailableDates(Place $place, ?string $from = null, ?string $to = null): ?array
    {
        if (
            $place->status !== PlaceStatus::Active
            || $place->review_status !== PlaceReviewStatus::Approved
        ) {
            return null;
        }

        $windowStart = $from !== null
            ? CarbonImmutable::parse($from)->startOfDay()
            : CarbonImmutable::now()->startOfDay();

        $windowEnd = $to !== null
            ? CarbonImmutable::parse($to)->startOfDay()
            : $windowStart->addMonths(self::DEFAULT_WINDOW_MONTHS);

        // A `to` before `from` collapses to a single day rather than erroring.
        if ($windowEnd->lessThan($windowStart)) {
            $windowEnd = $windowStart;
        }

        // Cap the span so a far-future blocking can't force a huge expansion.
        $maxEnd = $windowStart->addMonths(self::MAX_WINDOW_MONTHS);
        if ($windowEnd->greaterThan($maxEnd)) {
            $windowEnd = $maxEnd;
        }

        // De-dupe overlapping/adjacent blockings into a unique, sorted day set.
        $dates = array_keys($this->expandBlockedDays($place, $windowStart, $windowEnd));
        sort($dates);

        return [
            'place_id' => $place->id,
            'from' => $windowStart->toDateString(),
            'to' => $windowEnd->toDateString(),
            'dates' => $dates,
            'ranges' => $this->mergeIntoRanges($dates),
        ];
    }

    /**
     * Availability + pricing quote for a specific stay — the source of truth
     * the mobile checkout page reads before the guest commits to book. Pricing
     * is per-day inclusive: every calendar day from check-in to check-out is
     * billed at that day's weekday rate (falling back to the base price when
     * the host left that weekday at 0). The stay is bookable only when no day
     * in the range is blocked AND the party fits within max_guests.
     *
     * Returns null when the place isn't publicly visible so the controller can
     * 404 — same contract as the other read endpoints.
     *
     * @param  string  $checkIn  Inclusive first day (Y-m-d).
     * @param  string  $checkOut  Inclusive last day (Y-m-d).
     * @param  int|null  $guests  Party size; when given, checked against max_guests.
     * @return array{place_id: string, check_in: string, check_out: string, days: int, guests: int|null, max_guests: int|null, currency: string, dates_available: bool, guests_ok: bool, price_ok: bool, bookable: bool, unavailable_dates: list<string>, breakdown: list<array{date: string, weekday: string, price: int, available: bool}>, pricing: array{subtotal: float, subtotal_minor: int, commission_rate: float, commission_amount: float, commission_amount_minor: int, vat_rate: float, vat_amount: float, vat_amount_minor: int, total: float, total_minor: int}}|null
     */
    public function quote(Place $place, string $checkIn, string $checkOut, ?int $guests = null): ?array
    {
        if (
            $place->status !== PlaceStatus::Active
            || $place->review_status !== PlaceReviewStatus::Approved
        ) {
            return null;
        }

        $start = CarbonImmutable::parse($checkIn)->startOfDay();
        $end = CarbonImmutable::parse($checkOut)->startOfDay();
        if ($end->lessThan($start)) {
            $end = $start;
        }

        $blocked = $this->expandBlockedDays($place, $start, $end);

        $breakdown = [];
        $unavailable = [];
        $subtotal = 0;

        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addDay()) {
            $date = $cursor->toDateString();
            $isBlocked = isset($blocked[$date]);
            $price = $this->dayPrice($place, $cursor);

            if ($isBlocked) {
                $unavailable[] = $date;
            }

            $breakdown[] = [
                'date' => $date,
                'weekday' => strtolower($cursor->format('l')),
                'price' => $price,
                'available' => ! $isBlocked,
            ];
            $subtotal += $price;
        }

        $maxGuests = $place->max_guests;
        // Fail closed on a null capacity: publish/update forms require 1-50,
        // so a live place without one is misconfigured data — don't treat it
        // as "unlimited".
        $guestsOk = $guests === null || ($maxGuests !== null && $guests <= $maxGuests);
        $datesAvailable = $unavailable === [];
        // Every night resolved to 0 (weekday columns AND base price unset) —
        // the stay would total SR 0, which the gateway rejects anyway.
        $priceOk = $subtotal > 0;

        return [
            'place_id' => $place->id,
            'check_in' => $start->toDateString(),
            'check_out' => $end->toDateString(),
            'days' => $start->diffInDays($end) + 1,
            'guests' => $guests,
            'max_guests' => $maxGuests,
            'currency' => 'SAR',
            'dates_available' => $datesAvailable,
            'guests_ok' => $guestsOk,
            'price_ok' => $priceOk,
            'bookable' => $datesAvailable && $guestsOk && $priceOk,
            'unavailable_dates' => $unavailable,
            'breakdown' => $breakdown,
            'pricing' => $this->computePricing($subtotal),
        ];
    }

    /**
     * Compute the money breakdown for a stay. The guest pays the booking amount
     * (sum of nightly prices) plus VAT on that amount. Calm's commission is a
     * SEPARATE line taken from the host's payout — it is NOT added to the
     * guest's total. Both rates come from settings (commission_percentage /
     * vat_percentage) and fall back to 15%.
     *
     * All maths runs in halalas (integer minor units) so there's no floating
     * point drift, and `total_minor` is exactly what the payment gateway charges.
     *
     * @return array{subtotal: float, subtotal_minor: int, commission_rate: float, commission_amount: float, commission_amount_minor: int, vat_rate: float, vat_amount: float, vat_amount_minor: int, total: float, total_minor: int}
     */
    private function computePricing(int $subtotal): array
    {
        $rates = $this->settings->byKeys(['commission_percentage', 'vat_percentage']);
        $commissionRate = $this->rate($rates['commission_percentage'] ?? null, self::DEFAULT_COMMISSION_PERCENTAGE);
        $vatRate = $this->rate($rates['vat_percentage'] ?? null, self::DEFAULT_VAT_PERCENTAGE);

        $bookingAmountMinor = $subtotal * 100;
        $commissionMinor = (int) round($bookingAmountMinor * $commissionRate / 100);
        $vatMinor = (int) round($bookingAmountMinor * $vatRate / 100);
        $totalMinor = $bookingAmountMinor + $vatMinor; // commission is host-side

        return [
            'subtotal' => $bookingAmountMinor / 100,
            'subtotal_minor' => $bookingAmountMinor,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionMinor / 100,
            'commission_amount_minor' => $commissionMinor,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatMinor / 100,
            'vat_amount_minor' => $vatMinor,
            'total' => $totalMinor / 100,
            'total_minor' => $totalMinor,
        ];
    }

    /** Parse a settings percentage string, falling back when missing/blank. */
    private function rate(?string $value, float $default): float
    {
        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * The price for a single day: the host's per-weekday rate, falling back to
     * the base price when that weekday column is 0/null (the documented "leave
     * at 0 to use the base price" convention from the host edit form).
     */
    private function dayPrice(Place $place, CarbonImmutable $day): int
    {
        $column = Place::PRICE_COLUMNS[strtolower($day->format('l'))];

        return (int) ($place->{$column} ?: $place->price);
    }

    /**
     * The set of unavailable Y-m-d days inside [$start, $end] (inclusive). A day
     * is unavailable if a host blocking covers it OR an active booking occupies
     * it (confirmed, or a pending hold that hasn't expired). Shared by the
     * calendar feed and the quote so every surface reads availability identically.
     *
     * @return array<string, true>
     */
    private function expandBlockedDays(Place $place, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $set = [];

        // Host blocks. Plain string comparison keeps the (place_id, start_date,
        // end_date) index usable (whereDate's DATE() wrapper would defeat it).
        $blockings = $place->blockings()
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->get(['start_date', 'end_date']);

        foreach ($blockings as $blocking) {
            $this->fillDays($set, $blocking->start_date->toDateString(), $blocking->end_date->toDateString(), $start, $end);
        }

        // Active bookings holding the calendar (confirmed + live pending holds).
        // Multi-unit places (place_units rows) are capacity-counted: a day only
        // blocks once EVERY unit is taken — N-1 overlapping bookings still
        // leave it open. Classic places have capacity 1, i.e. the old rule.
        $capacity = max(1, $place->units()->count());

        $bookings = $place->bookings()
            ->activeHold()
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->get(['start_date', 'end_date']);

        $counts = [];
        foreach ($bookings as $booking) {
            $this->countDays($counts, $booking->start_date->toDateString(), $booking->end_date->toDateString(), $start, $end);
        }

        foreach ($counts as $day => $held) {
            if ($held >= $capacity) {
                $set[$day] = true;
            }
        }

        return $set;
    }

    /**
     * Mark every day of an inclusive [$rangeStart, $rangeEnd] range that falls
     * inside the [$windowStart, $windowEnd] window into $set (by reference).
     *
     * @param  array<string, true>  $set
     */
    private function fillDays(array &$set, string $rangeStart, string $rangeEnd, CarbonImmutable $windowStart, CarbonImmutable $windowEnd): void
    {
        $cursor = CarbonImmutable::parse($rangeStart);
        $clampEnd = CarbonImmutable::parse($rangeEnd);

        if ($cursor->lessThan($windowStart)) {
            $cursor = $windowStart;
        }
        if ($clampEnd->greaterThan($windowEnd)) {
            $clampEnd = $windowEnd;
        }

        while ($cursor->lessThanOrEqualTo($clampEnd)) {
            $set[$cursor->toDateString()] = true;
            $cursor = $cursor->addDay();
        }
    }

    /**
     * Same clamped walk as fillDays, but INCREMENTS a per-day counter instead
     * of flagging — how many bookings hold each day (multi-unit capacity math).
     *
     * @param  array<string, int>  $counts
     */
    private function countDays(array &$counts, string $rangeStart, string $rangeEnd, CarbonImmutable $windowStart, CarbonImmutable $windowEnd): void
    {
        $cursor = CarbonImmutable::parse($rangeStart);
        $clampEnd = CarbonImmutable::parse($rangeEnd);

        if ($cursor->lessThan($windowStart)) {
            $cursor = $windowStart;
        }
        if ($clampEnd->greaterThan($windowEnd)) {
            $clampEnd = $windowEnd;
        }

        while ($cursor->lessThanOrEqualTo($clampEnd)) {
            $counts[$cursor->toDateString()] = ($counts[$cursor->toDateString()] ?? 0) + 1;
            $cursor = $cursor->addDay();
        }
    }

    /**
     * The place's own upcoming blockings, for the host's availability manager.
     * Drops ranges that have already fully elapsed (end_date before today) so
     * the host only manages dates that still matter. Ordered chronologically.
     *
     * @return Collection<int, PlaceBlocking>
     */
    public function upcomingBlockingsForHost(Place $place): Collection
    {
        return $place->blockings()
            ->where('end_date', '>=', CarbonImmutable::now()->toDateString())
            ->orderBy('start_date')
            ->get();
    }

    /**
     * Block an inclusive [start_date, end_date] range on the place (host marks
     * the place unavailable). Overlaps with existing blockings are harmless —
     * the read API de-duplicates days when it expands them for the calendar.
     * Overlaps with active bookings are refused: blocking doesn't cancel the
     * stay, it would only hide a real conflict.
     *
     * @param  array{start_date: string, end_date: string, reason?: string|null}  $data
     */
    public function block(Place $place, array $data): PlaceBlocking
    {
        $bookingConflict = $place->bookings()
            ->activeHold()
            ->where('start_date', '<=', $data['end_date'])
            ->where('end_date', '>=', $data['start_date'])
            ->exists();

        if ($bookingConflict) {
            throw ValidationException::withMessages([
                'start_date' => __('Those dates include a confirmed or pending booking. Blocking them would not cancel it — contact support to cancel a booking.'),
            ]);
        }

        return $place->blockings()->create([
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'] ?? null,
        ]);
    }

    /** Lift a previously-blocked range — the place becomes available again. */
    public function unblock(PlaceBlocking $blocking): void
    {
        // Imported (iCal) blocks mirror an external calendar — deleting one
        // here would just resurrect on the next sync. They're freed by
        // cancelling on the source platform or removing the feed.
        if ($blocking->isImported()) {
            throw ValidationException::withMessages([
                'blocking' => __('This block comes from a connected calendar. Cancel it on the other platform or remove the feed instead.'),
            ]);
        }

        $blocking->delete();
    }

    /**
     * Fold a sorted, unique list of Y-m-d strings into contiguous ranges —
     * consecutive calendar days collapse into one {start_date, end_date}.
     *
     * @param  list<string>  $dates  Sorted, unique Y-m-d strings.
     * @return list<array{start_date: string, end_date: string}>
     */
    private function mergeIntoRanges(array $dates): array
    {
        $ranges = [];

        foreach ($dates as $date) {
            $last = array_key_last($ranges);

            if (
                $last !== null
                && CarbonImmutable::parse($ranges[$last]['end_date'])->addDay()->toDateString() === $date
            ) {
                $ranges[$last]['end_date'] = $date;

                continue;
            }

            $ranges[] = ['start_date' => $date, 'end_date' => $date];
        }

        return $ranges;
    }
}
