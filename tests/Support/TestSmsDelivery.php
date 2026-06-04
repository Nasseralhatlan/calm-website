<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\SmsDeliveryContract;

/**
 * In-memory SMS sink for tests. Captures every send so tests can inspect
 * what would have been delivered and pull the OTP back out of the message body.
 */
class TestSmsDelivery implements SmsDeliveryContract
{
    /** @var array<int, array{phone: string, message: string}> */
    public array $sent = [];

    public function send(string $phone, string $message): void
    {
        $this->sent[] = compact('phone', 'message');
    }

    public function lastCode(): string
    {
        $last = end($this->sent) ?: ['message' => ''];
        preg_match('/\d{6}/', $last['message'], $m);

        return $m[0] ?? '';
    }
}
