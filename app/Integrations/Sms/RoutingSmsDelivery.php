<?php

declare(strict_types=1);

namespace App\Integrations\Sms;

use App\Contracts\SmsDeliveryContract;
use App\Support\MockPhoneRegistry;

/**
 * Routes each SMS to the mock driver when the recipient is whitelisted
 * ({@see MockPhoneRegistry}), otherwise to the configured primary driver.
 *
 * Both the OTP flow and the notification job resolve this same contract, so a
 * whitelisted phone receives no real SMS at all — only the mock (logged) path.
 */
final class RoutingSmsDelivery implements SmsDeliveryContract
{
    public function __construct(
        private readonly SmsDeliveryContract $primary,
        private readonly SmsDeliveryContract $mock,
        private readonly MockPhoneRegistry $registry,
    ) {}

    public function send(string $phone, string $message): void
    {
        ($this->registry->has($phone) ? $this->mock : $this->primary)->send($phone, $message);
    }
}
