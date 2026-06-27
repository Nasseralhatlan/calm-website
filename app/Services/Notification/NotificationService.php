<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Jobs\SendNotificationChannels;
use App\Models\Booking;
use App\Models\NotificationBroadcast;
use App\Models\Place;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Builder;

/**
 * One entry point for every notification. Each call writes the in-app row
 * (instant feed, bilingual) and then fans the same message out to SMS + Expo
 * push (queued) — all three channels, every time. Outbound SMS/push are sent
 * in the recipient's language (`users.locale`, default Arabic); the in-app row
 * keeps both languages for the app to localize.
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

        // Outbound SMS/push follow the user's language (defaulting to Arabic).
        // The in-app row above still stores both languages for the app to localize.
        $en = $user->locale === 'en';

        SendNotificationChannels::dispatch(
            $user,
            $en ? $payload['title_en'] : $payload['title_ar'],
            $en ? $payload['body_en'] : $payload['body_ar'],
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

        return [
            'ar' => ['{ref}' => $ref, '{dates}' => $this->dateRange($booking, 'ar')],
            'en' => ['{ref}' => $ref, '{dates}' => $this->dateRange($booking, 'en')],
        ];
    }

    /**
     * The stay's date range — no times. Gregorian with a localized month name.
     * Arabic uses Arabic-Indic digits ("٢٣ يونيو ٢٠٢٦") so RTL formatting isn't
     * broken by Latin numerals; English uses Latin ("23 Jun 2026"). A single-day
     * stay collapses to one date.
     */
    private function dateRange(Booking $booking, string $locale): string
    {
        $start = $booking->start_date;
        if ($start === null) {
            return '—';
        }

        // Localized Gregorian month, Latin digits; for Arabic convert the digits
        // to Arabic-Indic so the RTL date reads cleanly.
        $arabicize = fn (string $s): string => strtr($s, [
            '0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤',
            '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩',
        ]);
        $label = fn ($d): string => $locale === 'ar'
            ? $arabicize($d->copy()->locale('ar')->translatedFormat('j F Y'))
            : $d->copy()->locale('en')->translatedFormat('j M Y');

        $startLabel = $label($start);

        $end = $booking->end_date;
        if ($end === null || $end->isSameDay($start)) {
            return $startLabel;
        }

        return $startLabel.' – '.$label($end);
    }
}
