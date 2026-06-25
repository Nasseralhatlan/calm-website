<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Enums\BookingStatus;
use App\Enums\OtpType;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\PlaceReview;
use App\Models\User;
use App\Services\Auth\AuthLoginService;
use App\Services\Otp\OtpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Account "deletion" = soft-delete + admin recovery. We never wipe data on the
 * user's request: the account is hidden (login disabled, listings pulled) and
 * the live phone/email are freed for re-signup, but everything is retained so
 * support can restore it. An optional, config-gated purge (config/account.php)
 * does the real PII scrub later if a retention window is set.
 */
final class AccountDeletionService
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly AuthLoginService $login,
    ) {}

    /**
     * Soft-delete the account after an OTP re-confirm. Returns the forget-cookie
     * the controller attaches so the client drops its JWT cookie too.
     */
    public function requestDeletion(User $user, string $code): Cookie
    {
        abort_if($user->role === UserRole::Admin, 422, 'Admin accounts cannot be self-deleted.');

        if (! $this->otp->verify($user, OtpType::Phone, $code)) {
            abort(422, 'Invalid or expired code.');
        }

        if ($reason = $this->blockingObligation($user)) {
            abort(422, $reason);
        }

        DB::transaction(function () use ($user): void {
            // Preserve the originals for support lookup, then free the unique
            // phone/email so the same number can register a fresh account.
            $user->forceFill([
                'deleted_phone' => $user->phone,
                'deleted_email' => $user->email,
                'phone' => null,
                'email' => null,
            ])->save();

            // Pull the host's listings out of search/booking (places are queried
            // directly, not via the user). Kept (soft-deleted) for restore.
            $user->places()->get()->each->delete();

            $user->delete();
        });

        $this->login->invalidate();

        return $this->login->forgetCookie();
    }

    /**
     * Bring a soft-deleted account back (support/admin only). Restores the user
     * and their listings, and re-claims the original phone/email when still free.
     */
    public function restore(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $update = [];

            if ($user->deleted_phone !== null && ! $this->identifierTaken('phone', $user->deleted_phone)) {
                $update['phone'] = $user->deleted_phone;
                $update['deleted_phone'] = null;
            }
            if ($user->deleted_email !== null && ! $this->identifierTaken('email', $user->deleted_email)) {
                $update['email'] = $user->deleted_email;
                $update['deleted_email'] = null;
            }
            if ($update !== []) {
                $user->forceFill($update)->save();
            }

            $user->restore();
            $user->places()->onlyTrashed()->restore();
        });
    }

    /**
     * The irreversible part — only run by PurgeDeletedAccounts when a retention
     * window is configured. Scrubs PII and removes purely-personal child rows.
     */
    public function purge(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->deviceTokens()->delete();
            $user->otps()->delete();
            $user->userNotifications()->delete();
            $user->likedPlaces()->detach();
            PlaceReview::query()->where('guest_user_id', $user->id)->update(['guest_user_id' => null]);

            $avatar = $user->avatar;
            if ($avatar !== null && $avatar !== '' && ! str_starts_with($avatar, 'http')) {
                Storage::disk('s3')->delete($avatar);
            }

            $user->forceFill([
                'name' => null, 'avatar' => null, 'gender' => null, 'age' => null, 'birth_date' => null,
                'phone' => null, 'email' => null, 'deleted_phone' => null, 'deleted_email' => null,
                'bank' => null, 'bank_account' => null, 'country_id' => null, 'password' => null,
                'phone_verified_at' => null, 'email_verified_at' => null,
            ])->save();
        });
    }

    /**
     * A human message when the account has an in-flight obligation that must be
     * settled before deletion, or null when it's clear to delete.
     */
    private function blockingObligation(User $user): ?string
    {
        $active = [BookingStatus::PendingPayment->value, BookingStatus::Confirmed->value];

        if (Booking::query()->where('guest_user_id', $user->id)->whereIn('booking_status', $active)->exists()) {
            return 'You have upcoming bookings. Please complete or cancel them before deleting your account.';
        }

        $hostBlocked = Booking::query()
            ->where('host_user_id', $user->id)
            ->where(function ($q) use ($active): void {
                $q->whereIn('booking_status', $active)
                    ->orWhere(fn ($w) => $w
                        ->where('booking_status', BookingStatus::Completed->value)
                        ->where('payout_status', '!=', 'paid'));
            })
            ->exists();

        if ($hostBlocked) {
            return 'Your listings have active bookings or pending payouts. Please resolve them before deleting your account.';
        }

        return null;
    }

    private function identifierTaken(string $column, string $value): bool
    {
        return User::query()->where($column, $value)->exists();
    }
}
