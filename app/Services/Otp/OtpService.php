<?php

declare(strict_types=1);

namespace App\Services\Otp;

use App\Contracts\SmsDeliveryContract;
use App\Enums\OtpType;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

final class OtpService
{
    private const OTP_LENGTH = 6;
    private const TTL_MINUTES = 3;
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly SmsDeliveryContract $sms,
    ) {}

    /**
     * Issue an OTP for a user + channel.
     *
     * If an active OTP already exists (not used, not expired, attempts not exhausted),
     * that record is returned as-is — no new code generated, no new SMS dispatched.
     * A fresh code + SMS only happens when the prior one is used, expired, or locked.
     */
    public function issue(User $user, OtpType $type, string $identifier): Otp
    {
        $active = $this->findActive($user, $type);

        if ($active) {
            return $active;
        }

        $plain = $this->generateCode();

        $otp = Otp::query()->create([
            'user_id' => $user->id,
            'type' => $type->value,
            'otp' => Hash::make($plain),
            'attempts' => 0,
            'used' => false,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $this->dispatch($type, $identifier, $plain);

        return $otp;
    }

    /**
     * Verify a submitted code. The OTP is locked (marked used) when:
     *  - the code is correct (success path), OR
     *  - the user has just exhausted MAX_ATTEMPTS wrong tries.
     * Either way the same record can't be retried after that point — a new one
     * must be issued via issue().
     */
    public function verify(User $user, OtpType $type, string $code): bool
    {
        $otp = $this->findActive($user, $type);

        if (! $otp) {
            return false;
        }

        $otp->increment('attempts');

        if (Hash::check($code, $otp->otp)) {
            DB::transaction(function () use ($otp, $user, $type): void {
                $otp->update(['used' => true]);

                $user->forceFill([
                    $type === OtpType::Phone ? 'phone_verified_at' : 'email_verified_at' => now(),
                ])->save();
            });

            return true;
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            $otp->update(['used' => true]);
        }

        return false;
    }

    /**
     * The most recent OTP for this user + type that is still usable:
     * not used, not expired, and not yet at the attempt cap.
     */
    private function findActive(User $user, OtpType $type): ?Otp
    {
        return Otp::query()
            ->where('user_id', $user->id)
            ->where('type', $type->value)
            ->where('used', false)
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    private function dispatch(OtpType $type, string $identifier, string $code): void
    {
        $message = "Your Calm verification code is: {$code}";

        match ($type) {
            OtpType::Phone => $this->sms->send($identifier, $message),
            OtpType::Email => Mail::raw($message, function ($mail) use ($identifier): void {
                $mail->to($identifier)->subject('Your Calm verification code');
            }),
        };
    }

    private function generateCode(): string
    {
        // Dev convenience: the mock SMS driver doesn't deliver to a real phone,
        // so a random code would just clutter the log and force the dev to
        // open it on every login. Hard-code "111111" so anyone running the
        // app locally (or in CI) can sign in without checking laravel.log.
        // The real `sms_saudi` driver still gets a fresh random code.
        if (config('sms.driver') === 'mock') {
            return str_repeat('1', self::OTP_LENGTH);
        }

        $code = '';

        for ($i = 0; $i < self::OTP_LENGTH; $i++) {
            $code .= (string) random_int(0, 9);
        }

        return $code;
    }
}
