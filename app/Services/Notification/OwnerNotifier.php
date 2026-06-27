<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Mail\OwnerAlert;
use App\Models\Booking;
use App\Models\Place;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Internal email alerts to the business owners on important events. Disabled
 * (no-op) unless OWNER_EMAILS is configured. Failures are swallowed so an email
 * hiccup never breaks the booking/place flow it's attached to.
 */
final class OwnerNotifier
{
    /** A guest paid and the booking is confirmed — revenue. */
    public function bookingPaid(Booking $booking): void
    {
        $this->send('💰 New booking — '.$booking->reference, [
            'A booking was just paid and confirmed.',
            'Reference: '.$booking->reference,
            'Place: '.($booking->place?->title ?? '—'),
            'Dates: '.$booking->start_date?->toDateString().' → '.$booking->end_date?->toDateString(),
            'Amount: SR '.number_format($booking->total / 100, 2),
            'Guest: '.($booking->guest?->phone ?? '—'),
        ]);
    }

    /** Moyasar invoice/payment init failed for a booking. */
    public function paymentFailed(Booking $booking, string $error): void
    {
        $this->send('⚠️ Payment failed — '.$booking->reference, [
            'A booking payment could not be started.',
            'Reference: '.$booking->reference,
            'Place: '.($booking->place?->title ?? '—'),
            'Amount: SR '.number_format($booking->total / 100, 2),
            'Guest: '.($booking->guest?->phone ?? '—'),
            'Error: '.$error,
        ]);
    }

    /** A host submitted/resubmitted a listing that needs review. */
    public function placeSubmitted(Place $place): void
    {
        $this->send('🏠 New place submitted — '.($place->title ?? $place->id), [
            'A place was submitted for review.',
            'Place: '.($place->title ?? '—'),
            'Place ID: '.$place->id,
            'Host: '.($place->host?->name ?? '—').' ('.($place->host?->phone ?? '—').')',
        ]);
    }

    /**
     * @param  list<string>  $lines
     */
    private function send(string $subject, array $lines): void
    {
        $emails = config('owner.emails', []);

        if ($emails === []) {
            return; // owner alerts disabled
        }

        try {
            Mail::to($emails)->queue(new OwnerAlert($subject, $lines));
        } catch (Throwable $e) {
            Log::warning('Owner alert failed', ['subject' => $subject, 'error' => $e->getMessage()]);
        }
    }
}
