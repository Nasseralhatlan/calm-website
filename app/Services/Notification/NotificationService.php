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
 * in Arabic for now; the in-app row keeps both languages for the app.
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

        // All outbound SMS/push are sent in Arabic for now.
        // (The in-app row above still stores both languages for the app to localize.)
        SendNotificationChannels::dispatch(
            $user,
            $payload['title_ar'],
            $payload['body_ar'],
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
        $checkIn = $this->checkInAt($booking);
        $checkOut = $booking->checkoutAt();
        $ref = $booking->reference;

        $this->notify($booking->guest, [
            'type' => 'booking_confirmed',
            'title_ar' => 'تم تأكيد حجزك',
            'title_en' => 'Your booking is confirmed',
            'body_ar' => "تم تأكيد حجزك في {$place}. الدخول {$this->stayLabel($checkIn, 'ar')}، والخروج {$this->stayLabel($checkOut, 'ar')}. رقم الحجز: {$ref}. نتمنى لك إقامة سعيدة.",
            'body_en' => "Your booking at {$place} is confirmed. Check-in {$this->stayLabel($checkIn, 'en')}, check-out {$this->stayLabel($checkOut, 'en')}. Booking ref: {$ref}. Enjoy your stay!",
            'data' => ['booking_id' => $booking->id, 'place_id' => $booking->place_id],
        ]);
    }

    public function bookingCancelled(Booking $booking): void
    {
        $place = $booking->place?->title ?? 'المكان';

        $ref = $booking->reference;

        $this->notify($booking->guest, [
            'type' => 'booking_cancelled',
            'title_ar' => 'تم إلغاء حجزك',
            'title_en' => 'Your booking was cancelled',
            'body_ar' => "تم إلغاء حجزك في {$place}. رقم الحجز: {$ref}.",
            'body_en' => "Your booking at {$place} has been cancelled. Booking ref: {$ref}.",
            'data' => ['booking_id' => $booking->id, 'place_id' => $booking->place_id],
        ]);
    }

    /**
     * Host-initiated cancellation of a confirmed booking. The guest gets a warm
     * apology; the host gets a calm confirmation. Wording is intentionally soft —
     * cancellations affect trust in the platform.
     */
    public function bookingCanceledByHost(Booking $booking): void
    {
        $place = $booking->place?->title ?? 'المكان';
        $ref = $booking->reference;

        $this->notify($booking->guest, [
            'type' => 'booking_canceled_by_host',
            'title_ar' => 'نعتذر، تم إلغاء حجزك',
            'title_en' => 'Sorry — your booking was cancelled',
            'body_ar' => "نأسف لإبلاغك بأنه تم إلغاء حجزك في \"{$place}\" من قِبل المضيف. رقم الحجز: {$ref}. نعتذر عن الإزعاج، وفريقنا سعيد بمساعدتك في إيجاد بديل مناسب.",
            'body_en' => "We're sorry to let you know your booking at \"{$place}\" was cancelled by the host. Booking ref: {$ref}. Apologies for the inconvenience — our team is happy to help you find a great alternative.",
            'data' => ['booking_id' => $booking->id, 'place_id' => $booking->place_id],
        ]);

        $this->notify($booking->host, [
            'type' => 'booking_canceled_by_host',
            'title_ar' => 'تم إلغاء الحجز',
            'title_en' => 'Booking cancelled',
            'body_ar' => "تم إلغاء حجز الضيف في \"{$place}\" بناءً على طلبك. رقم الحجز: {$ref}. شكراً لإشعارنا.",
            'body_en' => "The guest's booking at \"{$place}\" has been cancelled per your request. Booking ref: {$ref}. Thanks for letting us know.",
            'data' => ['booking_id' => $booking->id, 'place_id' => $booking->place_id],
        ]);
    }

    /**
     * Platform/admin cancellation of a confirmed booking — typically at the
     * guest's request. The guest gets a friendly confirmation; the host a gentle
     * heads-up. Soft wording, since this touches the business relationship.
     */
    public function bookingCanceledByAdmin(Booking $booking): void
    {
        $place = $booking->place?->title ?? 'المكان';
        $ref = $booking->reference;

        $this->notify($booking->guest, [
            'type' => 'booking_canceled_by_admin',
            'title_ar' => 'تم إلغاء حجزك',
            'title_en' => 'Your booking was cancelled',
            'body_ar' => "تم إلغاء حجزك في \"{$place}\" بناءً على طلبك. رقم الحجز: {$ref}. نتمنى استضافتك مجدداً قريباً.",
            'body_en' => "Your booking at \"{$place}\" has been cancelled as requested. Booking ref: {$ref}. We'd love to host you again soon.",
            'data' => ['booking_id' => $booking->id, 'place_id' => $booking->place_id],
        ]);

        $this->notify($booking->host, [
            'type' => 'booking_canceled_by_admin',
            'title_ar' => 'تم إلغاء حجز',
            'title_en' => 'A booking was cancelled',
            'body_ar' => "نود إعلامك بأنه تم إلغاء حجز في \"{$place}\" بناءً على طلب الضيف. رقم الحجز: {$ref}. نعتذر عن أي إزعاج.",
            'body_en' => "Just so you know, a booking at \"{$place}\" was cancelled at the guest's request. Booking ref: {$ref}. Apologies for any inconvenience.",
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
            'body_ar' => "استلمنا \"{$title}\" وهو الآن قيد المراجعة. سنخبرك فور اكتمالها ليظهر مكانك في تطبيق كالم.",
            'body_en' => "We received \"{$title}\" — it's now under review. We'll let you know once it's live on the Calm app.",
            'data' => ['place_id' => $place->id],
        ]);
    }

    public function hostNewBooking(Booking $booking): void
    {
        $place = $booking->place?->title ?? 'مكانك';
        $checkIn = $this->checkInAt($booking);
        $checkOut = $booking->checkoutAt();
        $ref = $booking->reference;

        $this->notify($booking->host, [
            'type' => 'host_new_booking',
            'title_ar' => 'لديك حجز جديد',
            'title_en' => 'You have a new booking',
            'body_ar' => "لديك حجز جديد على \"{$place}\". الدخول {$this->stayLabel($checkIn, 'ar')}، والخروج {$this->stayLabel($checkOut, 'ar')}. رقم الحجز: {$ref}.",
            'body_en' => "You've got a new booking on \"{$place}\". Check-in {$this->stayLabel($checkIn, 'en')}, check-out {$this->stayLabel($checkOut, 'en')}. Booking ref: {$ref}.",
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
            'body_ar' => "أصبح \"{$title}\" متاحاً للحجز الآن في تطبيق كالم.",
            'body_en' => "\"{$title}\" is now live and available for booking on the Calm app.",
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
