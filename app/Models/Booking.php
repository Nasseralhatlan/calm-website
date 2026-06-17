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

    protected $fillable = [
        'place_id',
        'guest_user_id',
        'host_user_id',
        'booking_status',
        'start_date',
        'end_date',
        'check_in_time',
        'check_out_time',
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
            'booking_status' => BookingStatus::class,
            'payment_status_check_attempts' => 'integer',
            'payment_response' => 'array',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_user_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
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
}
