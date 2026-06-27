<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Jobs\SendNotificationChannels;
use App\Models\Booking;
use App\Models\NotificationBroadcast;
use App\Models\Place;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * One entry point for every notification. Each call writes the in-app row
 * (instant feed, bilingual) and then fans the same message out to SMS + Expo
 * push (queued) — all three channels, every time. Outbound SMS/push are sent
 * in English for now; the in-app row keeps both languages for the app.
 *
 * `$payload` shape: ['type', 'title_ar', 'title_en', 'body_ar', 'body_en', 'data'?].
 */
final class NotificationService
{
    /**
     * Notify a single user.
     *
     * @param  array<string, mixed>  $payload
     */
    public function notify(User $user, array $payload): UserNotification
    {
        $notification = $user->userNotifications()->create([
            'type' => $payload['type'],
            'title_ar' => $payload['title_ar'],
            'title_en' => $payload['title_en'],
            'body_ar' => $payload['body_ar'],
            'body_en' => $payload['body_en'],
            'data' => $payload['data'] ?? null,
        ]);

        // All outbound SMS/push are sent in English for now.
        // (The in-app row above still stores both languages for the app to localize.)
        SendNotificationChannels::dispatch(
            $user,
            $payload['title_en'],
            $payload['body_en'],
            $payload['data'] ?? [],
        );

        return $notification;
    }

    /**
     * Admin "send updates to users" — fan a message out to an audience. Writes
     * an audit row, then notifies each user (chunked). Large audiences need a
     * queue worker so the SMS/push jobs don't run inline.
     *
     * @param  array<string, mixed>  $payload
     */
    public function broadcast(User $admin, string $audience, array $payload): NotificationBroadcast
    {
        $payload['type'] = 'broadcast';

        $broadcast = NotificationBroadcast::query()->create([
            'admin_user_id' => $admin->id,
            'audience' => $audience,
            'title_ar' => $payload['title_ar'],
            'title_en' => $payload['title_en'],
            'body_ar' => $payload['body_ar'],
            'body_en' => $payload['body_en'],
            'data' => $payload['data'] ?? null,
        ]);

        $count = 0;
        $this->audienceQuery($audience)->chunkById(500, function ($users) use ($payload, &$count): void {
            foreach ($users as $user) {
                $this->notify($user, $payload);
                $count++;
            }
        });

        $broadcast->update(['recipients_count' => $count]);

        return $broadcast;
    }

    /**
     * @return Builder<User>
     */
    private function audienceQuery(string $audience): Builder
    {
        return match ($audience) {
            'hosts' => User::query()->whereHas('places'),
            'guests' => User::query()->whereDoesntHave('places'),
            default => User::query(), // all
        };
    }

    // ── System-event notifications ───────────────────────────────────────────
    //
    // Message text lives in config/notifications.php (one editable place, no
    // user-generated content). Each method only resolves the recipient + the
    // system values (booking ref, dates, reason) and fires notify().

    public function bookingConfirmed(Booking $booking): void
    {
        $this->notify($booking->guest, $this->compose(
            'booking_confirmed', 'guest', $this->bookingVars($booking),
            ['booking_id' => $booking->id, 'place_id' => $booking->place_id],
        ));
    }

    public function bookingCancelled(Booking $booking): void
    {
        $this->notify($booking->guest, $this->compose(
            'booking_cancelled', 'guest', $this->refVars($booking),
            ['booking_id' => $booking->id, 'place_id' => $booking->place_id],
        ));
    }

    /**
     * Host-initiated cancellation of a confirmed booking — both parties told.
     */
    public function bookingCanceledByHost(Booking $booking): void
    {
        $vars = $this->refVars($booking);
        $data = ['booking_id' => $booking->id, 'place_id' => $booking->place_id];

        $this->notify($booking->guest, $this->compose('booking_canceled_by_host', 'guest', $vars, $data));
        $this->notify($booking->host, $this->compose('booking_canceled_by_host', 'host', $vars, $data));
    }

    /**
     * Platform/admin cancellation of a confirmed booking — both parties told.
     */
    public function bookingCanceledByAdmin(Booking $booking): void
    {
        $vars = $this->refVars($booking);
        $data = ['booking_id' => $booking->id, 'place_id' => $booking->place_id];

        $this->notify($booking->guest, $this->compose('booking_canceled_by_admin', 'guest', $vars, $data));
        $this->notify($booking->host, $this->compose('booking_canceled_by_admin', 'host', $vars, $data));
    }

    public function placeSubmitted(Place $place): void
    {
        $this->notify($place->host, $this->compose(
            'place_submitted', 'host', ['ar' => [], 'en' => []], ['place_id' => $place->id],
        ));
    }

    public function hostNewBooking(Booking $booking): void
    {
        $this->notify($booking->host, $this->compose(
            'host_new_booking', 'host', $this->bookingVars($booking),
            ['booking_id' => $booking->id, 'place_id' => $booking->place_id],
        ));
    }

    public function placeApproved(Place $place): void
    {
        $this->notify($place->host, $this->compose(
            'place_approved', 'host', ['ar' => [], 'en' => []], ['place_id' => $place->id],
        ));
    }

    public function placeRejected(Place $place, ?string $reason = null): void
    {
        $reason = $reason !== null && $reason !== '' ? $reason : null;
        // A reasonless rejection uses the shorter template.
        $audience = $reason !== null ? 'host' : 'host_no_reason';

        $this->notify($place->host, $this->compose(
            'place_rejected', $audience,
            ['ar' => ['{reason}' => (string) $reason], 'en' => ['{reason}' => (string) $reason]],
            ['place_id' => $place->id],
            type: 'place_rejected',
        ));
    }

    /**
     * Build a notify() payload from a config template, interpolating the AR/EN
     * placeholder maps. `$vars` is `['ar' => ['{x}' => '…'], 'en' => [...]]`.
     *
     * @param  array{ar: array<string, string>, en: array<string, string>}  $vars
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function compose(string $key, string $audience, array $vars, array $data, ?string $type = null): array
    {
        $t = config("notifications.{$key}.{$audience}");

        return [
            'type' => $type ?? $key,
            'title_ar' => strtr($t['title_ar'], $vars['ar']),
            'title_en' => strtr($t['title_en'], $vars['en']),
            'body_ar' => strtr($t['body_ar'], $vars['ar']),
            'body_en' => strtr($t['body_en'], $vars['en']),
            'data' => $data,
        ];
    }

    /**
     * @return array{ar: array<string, string>, en: array<string, string>}
     */
    private function refVars(Booking $booking): array
    {
        $ref = (string) $booking->reference;

        return ['ar' => ['{ref}' => $ref], 'en' => ['{ref}' => $ref]];
    }

    /**
     * @return array{ar: array<string, string>, en: array<string, string>}
     */
    private function bookingVars(Booking $booking): array
    {
        $ref = (string) $booking->reference;
        $checkIn = $this->checkInAt($booking);
        $checkOut = $booking->checkoutAt();

        return [
            'ar' => ['{ref}' => $ref, '{checkIn}' => $this->stayLabel($checkIn, 'ar'), '{checkOut}' => $this->stayLabel($checkOut, 'ar')],
            'en' => ['{ref}' => $ref, '{checkIn}' => $this->stayLabel($checkIn, 'en'), '{checkOut}' => $this->stayLabel($checkOut, 'en')],
        ];
    }

    /** Check-in moment = the stay's start date at its check-in time. */
    private function checkInAt(Booking $booking): ?CarbonInterface
    {
        if ($booking->start_date === null) {
            return null;
        }

        return Carbon::parse($booking->start_date->toDateString().' '.($booking->check_in_time ?: '00:00'));
    }

    /**
     * Gregorian date + AM/PM time, localized month name with Latin digits.
     * The time is formatted separately so the meridiem stays "AM"/"PM" even in
     * the Arabic variant (translatedFormat would localize it otherwise).
     */
    private function stayLabel(?CarbonInterface $dt, string $locale): string
    {
        if ($dt === null) {
            return '—';
        }

        $date = $dt->copy()->locale($locale)->translatedFormat($locale === 'ar' ? 'j F Y' : 'j M Y');
        $time = $dt->format('g:i A');

        return $locale === 'ar' ? "{$date}، {$time}" : "{$date}, {$time}";
    }
}
