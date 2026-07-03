<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasUuids;

    /** Characters used in the support reference — no ambiguous 0/O/1/I/L. */
    private const REFERENCE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    protected static function booted(): void
    {
        // Assign a unique, support-friendly reference (e.g. CB-7K9P2Q) to every
        // booking on create — covers the API, seeders, tests, and the backfill.
        static::creating(function (self $booking): void {
            if (empty($booking->reference)) {
                $booking->reference = self::generateUniqueReference();
            }
        });
    }

    /** A short, human-readable reference unique across bookings. */
    public static function generateUniqueReference(): string
    {
        $max = strlen(self::REFERENCE_ALPHABET) - 1;

        do {
            $code = 'CB-';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::REFERENCE_ALPHABET[random_int(0, $max)];
            }
        } while (self::query()->where('reference', $code)->exists());

        return $code;
    }

    protected $fillable = [
        'place_id',
        'guest_user_id',
        'host_user_id',
        'booking_status',
        'start_date',
        'end_date',
        'check_in_time',
        'check_out_time',
        'checkout_next_day',
        'rules',
        'guests',
        'booking_price',
        'quantity',
        'booking_amount',
        'commission_rate',
        'commission_amount',
        'vat_rate',
        'vat_amount',
        'total',
        'payment_id',
        'payment_url',
        'payment_method',
        'payment_status',
        'payment_status_check_attempts',
        'payment_response',
        'payout_status',
        'paid_out_at',
        'payout_reference',
        'expires_at',
        'confirmed_at',
        'canceled_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'guests' => 'integer',
            'booking_price' => 'integer',
            'quantity' => 'integer',
            'booking_amount' => 'integer',
            'commission_rate' => 'float',
            'commission_amount' => 'integer',
            'vat_rate' => 'float',
            'vat_amount' => 'integer',
            'total' => 'integer',
            'checkout_next_day' => 'boolean',
            'booking_status' => BookingStatus::class,
            'payment_status_check_attempts' => 'integer',
            'payment_response' => 'array',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'canceled_at' => 'datetime',
            'paid_out_at' => 'datetime',
        ];
    }

    /**
     * What the host is owed for this booking, in minor units (halalas):
     * their gross booking amount minus Calm's commission. VAT is the guest's
     * money and is remitted, never part of the host payout.
     */
    public function hostNetMinor(): int
    {
        return (int) $this->booking_amount - (int) $this->commission_amount;
    }

    /**
     * Reconstruct the per-night rate lines for this stay from the place's
     * per-weekday price columns (places store SAR; bookings snapshot halalas).
     * Only trustworthy while the recomputed sum still equals the snapshotted
     * booking_amount — the host may have changed prices since the guest
     * booked. Returns null in that case so callers fall back to a
     * nights × average line instead of showing a made-up split.
     *
     * @return list<array{date: CarbonImmutable, price_minor: int}>|null
     */
    public function nightlyRates(): ?array
    {
        $place = $this->place;
        if ($place === null || $this->start_date === null || $this->end_date === null) {
            return null;
        }

        $start = CarbonImmutable::parse($this->start_date->toDateString());
        $end = CarbonImmutable::parse($this->end_date->toDateString());
        // end_date is the last occupied night (inclusive). The cap only guards
        // against pathological rows — real stays are days, not seasons.
        if ($end->lessThan($start) || $start->diffInDays($end) > 92) {
            return null;
        }

        $lines = [];
        $sum = 0;
        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addDay()) {
            $column = Place::PRICE_COLUMNS[strtolower($cursor->format('l'))];
            // Same fallback convention as the quote engine: a 0/null weekday
            // column means "use the base price".
            $priceMinor = (int) ($place->{$column} ?: $place->price) * 100;
            $lines[] = ['date' => $cursor, 'price_minor' => $priceMinor];
            $sum += $priceMinor;
        }

        return $sum === (int) $this->booking_amount ? $lines : null;
    }

    public function place(): BelongsTo
    {
        // withTrashed: a booking must always resolve its place even after the
        // place is archived (soft-deleted) — otherwise the guest's "My bookings",
        // host bookings, and the booking detail/resource lose the place block.
        // Archiving a place stops NEW bookings (the route binding 404s a trashed
        // place); existing bookings stay valid and readable.
        return $this->belongsTo(Place::class)->withTrashed();
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_user_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    /**
     * The guest's real checkout instant. end_date is the last *occupied* day;
     * checkout falls on end_date + 1 (the morning after) when checkout_next_day
     * is set — the overnight case — or on end_date itself for a same-day stay.
     * The day is an explicit place setting, not inferred from the times.
     */
    public function checkoutAt(): ?CarbonImmutable
    {
        if ($this->end_date === null) {
            return null;
        }

        $day = CarbonImmutable::parse($this->end_date->toDateString());
        if ($this->checkout_next_day) {
            $day = $day->addDay();
        }

        if ($this->check_out_time === null) {
            return $day->startOfDay();
        }

        [$hour, $minute] = array_pad(explode(':', $this->check_out_time), 2, '0');

        return $day->setTime((int) $hour, (int) $minute);
    }

    /**
     * The guest's real check-in instant: start_date at check_in_time. Unlike
     * checkout there's no next-day flag — check-in is always on start_date.
     * Falls back to the start of start_date when no time is set.
     */
    public function checkInAt(): ?CarbonImmutable
    {
        if ($this->start_date === null) {
            return null;
        }

        $day = CarbonImmutable::parse($this->start_date->toDateString());

        if ($this->check_in_time === null) {
            return $day->startOfDay();
        }

        [$hour, $minute] = array_pad(explode(':', $this->check_in_time), 2, '0');

        return $day->setTime((int) $hour, (int) $minute);
    }

    /** True while this booking is still holding its dates against the calendar. */
    public function isActiveHold(): bool
    {
        if (in_array($this->booking_status, [BookingStatus::Confirmed, BookingStatus::Completed], true)) {
            return true;
        }

        return $this->booking_status === BookingStatus::PendingPayment
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Bookings that currently occupy the calendar: confirmed/completed, plus
     * pending holds that haven't yet passed their expiry. Used by the
     * availability check so a place can't be double-booked.
     */
    public function scopeActiveHold(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereIn('booking_status', [BookingStatus::Confirmed->value, BookingStatus::Completed->value])
                ->orWhere(function (Builder $q): void {
                    $q->where('booking_status', BookingStatus::PendingPayment->value)
                        ->where(function (Builder $q): void {
                            $q->whereNull('expires_at')
                                ->orWhere('expires_at', '>', CarbonImmutable::now());
                        });
                });
        });
    }

    /**
     * Order the guest's "My bookings" list for the best experience: actionable
     * holds and upcoming stays first, then recent history, cancellations last.
     *
     *   1. pending_payment — awaiting payment, by nearest start_date (must act)
     *   2. confirmed       — upcoming, by nearest start_date
     *   3. completed       — past, most recently finished first (end_date desc)
     *   4. canceled_*      — last, most recently cancelled first (canceled_at desc)
     *
     * Each secondary key is wrapped in a CASE that is non-null only for its own
     * status bucket, so the bucket the primary CASE already separated is the only
     * one it sorts — making the multi-key order portable across SQLite and MySQL.
     */
    public function scopeOrderForGuestList(Builder $query): Builder
    {
        return $query
            ->orderByRaw(
                'CASE booking_status WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 ELSE 4 END',
                [
                    BookingStatus::PendingPayment->value,
                    BookingStatus::Confirmed->value,
                    BookingStatus::Completed->value,
                ],
            )
            ->orderByRaw(
                'CASE WHEN booking_status IN (?, ?) THEN start_date END ASC',
                [BookingStatus::PendingPayment->value, BookingStatus::Confirmed->value],
            )
            ->orderByRaw(
                'CASE WHEN booking_status = ? THEN end_date END DESC',
                [BookingStatus::Completed->value],
            )
            ->orderByRaw(
                'CASE WHEN booking_status IN (?, ?) THEN canceled_at END DESC',
                [BookingStatus::CanceledByHost->value, BookingStatus::CanceledByGuest->value],
            )
            ->orderByDesc('id'); // stable tiebreaker for equal sort keys
    }
}
