<?php

declare(strict_types=1);

namespace App\Services\Otp;

use App\Contracts\SmsDeliveryContract;
use App\Enums\OtpType;
use App\Models\Otp;
use App\Models\User;
use App\Support\MockPhoneRegistry;
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
        private readonly MockPhoneRegistry $mockPhones,
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

        $plain = $this->generateCode($type, $identifier);

        $otp = Otp::query()->create([
            'user_id' => $user->id,
            'type' => $type->value,
            'otp' => Hash::make($plain),
            'attempts' => 0,
            'used' => false,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $this->dispatch($type, $identifier, $plain, $user->locale);

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

    /**
     * Deliver the code in the recipient's language (default Arabic). Text lives
     * in config/notifications.php ('otp'); only the {code} is interpolated here.
     */
    private function dispatch(OtpType $type, string $identifier, string $code, string $locale): void
    {
        $en = $locale === 'en';
        $body = strtr(
            config($en ? 'notifications.otp.sms_en' : 'notifications.otp.sms_ar'),
            ['{code}' => $code],
        );

        match ($type) {
            OtpType::Phone => $this->sms->send($identifier, $body),
            OtpType::Email => Mail::raw($body, function ($mail) use ($identifier, $en): void {
                $mail->to($identifier)->subject(
                    config($en ? 'notifications.otp.email_subject_en' : 'notifications.otp.email_subject_ar'),
                );
            }),
        };
    }

    private function generateCode(OtpType $type, string $identifier): string
    {
        // Whitelisted phones (testers / App Store reviewers, from
        // SMS_MOCK_PHONES) always get the fixed code — on ANY driver, including
        // the real `sms_saudi` one in production — so a reviewer can sign in
        // without receiving a real SMS. This is the intended review path.
        if ($type === OtpType::Phone && $this->mockPhones->has($identifier)) {
            return str_repeat('1', self::OTP_LENGTH);
        }

        // Local/CI convenience: the mock SMS driver only logs, so a random code
        // is just noise — hand out "111111" so devs sign in without opening
        // laravel.log. NEVER in production: a mock driver there is a
        // misconfiguration (OtpServiceProvider refuses to bind it), and the
        // fixed code must never reach real users — that would be an auth bypass.
        if (config('sms.driver') === 'mock' && ! app()->isProduction()) {
            return str_repeat('1', self::OTP_LENGTH);
        }

        $code = '';

        for ($i = 0; $i < self::OTP_LENGTH; $i++) {
            $code .= (string) random_int(0, 9);
        }

        return $code;
    }
}
