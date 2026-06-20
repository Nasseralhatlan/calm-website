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
 * (instant feed) and then fans the same message out to SMS + Expo push (queued)
 * — all three channels, every time, in the recipient's language.
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

        $locale = $user->locale === 'en' ? 'en' : 'ar';

        SendNotificationChannels::dispatch(
            $user,
            $locale === 'en' ? $payload['title_en'] : $payload['title_ar'],
            $locale === 'en' ? $payload['body_en'] : $payload['body_ar'],
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

    public function bookingConfirmed(Booking $booking): void
    {
        $place = $booking->place?->title ?? ($booking->place_id ? 'مكانك' : '');

        $this->notify($booking->guest, [
            'type' => 'booking_confirmed',
            'title_ar' => 'تم تأكيد حجزك',
            'title_en' => 'Your booking is confirmed',
            'body_ar' => "تم تأكيد حجزك في {$place}. نتمنى لك إقامة سعيدة.",
            'body_en' => "Your booking at {$place} is confirmed. Enjoy your stay!",
            'data' => ['booking_id' => $booking->id, 'place_id' => $booking->place_id],
        ]);
    }

    public function bookingCancelled(Booking $booking): void
    {
        $place = $booking->place?->title ?? 'المكان';

        $this->notify($booking->guest, [
            'type' => 'booking_cancelled',
            'title_ar' => 'تم إلغاء حجزك',
            'title_en' => 'Your booking was cancelled',
            'body_ar' => "تم إلغاء حجزك في {$place}.",
            'body_en' => "Your booking at {$place} has been cancelled.",
            'data' => ['booking_id' => $booking->id, 'place_id' => $booking->place_id],
        ]);
    }

    public function placeSubmitted(Place $place): void
    {
        $title = $place->title ?? 'مكانك';

        $this->notify($place->host, [
            'type' => 'place_submitted',
            'title_ar' => 'تم استلام مكانك للمراجعة',
            'title_en' => 'Your place was submitted for review',
            'body_ar' => "استلمنا \"{$title}\" وهو الآن قيد المراجعة. سنخبرك فور اكتمالها.",
            'body_en' => "We received \"{$title}\" — it's now under review. We'll let you know once it's done.",
            'data' => ['place_id' => $place->id],
        ]);
    }

    public function hostNewBooking(Booking $booking): void
    {
        $place = $booking->place?->title ?? 'مكانك';

        $this->notify($booking->host, [
            'type' => 'host_new_booking',
            'title_ar' => 'لديك حجز جديد',
            'title_en' => 'You have a new booking',
            'body_ar' => "لديك حجز جديد على \"{$place}\".",
            'body_en' => "You've got a new booking on \"{$place}\".",
            'data' => ['booking_id' => $booking->id, 'place_id' => $booking->place_id],
        ]);
    }

    public function placeApproved(Place $place): void
    {
        $title = $place->title ?? 'مكانك';

        $this->notify($place->host, [
            'type' => 'place_approved',
            'title_ar' => 'تمت الموافقة على مكانك',
            'title_en' => 'Your place was approved',
            'body_ar' => "أصبح \"{$title}\" متاحاً للحجز الآن.",
            'body_en' => "\"{$title}\" is now live and available for booking.",
            'data' => ['place_id' => $place->id],
        ]);
    }

    public function placeRejected(Place $place, ?string $reason = null): void
    {
        $title = $place->title ?? 'مكانك';
        $reason = $reason !== null && $reason !== '' ? $reason : null;

        $this->notify($place->host, [
            'type' => 'place_rejected',
            'title_ar' => 'مكانك يحتاج إلى تعديلات',
            'title_en' => 'Your place needs changes',
            'body_ar' => $reason !== null
                ? "يحتاج \"{$title}\" إلى تعديلات: {$reason}"
                : "يحتاج \"{$title}\" إلى بعض التعديلات قبل الموافقة عليه.",
            'body_en' => $reason !== null
                ? "\"{$title}\" needs changes: {$reason}"
                : "\"{$title}\" needs some changes before it can be approved.",
            'data' => ['place_id' => $place->id],
        ]);
    }
}
