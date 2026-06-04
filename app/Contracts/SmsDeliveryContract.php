<?php

declare(strict_types=1);

namespace App\Contracts;

interface SmsDeliveryContract
{
    /**
     * Send a plain text message to a phone number.
     * Implementations are pure transport — they don't know or care that the
     * message body happens to be an OTP, a marketing message, etc.
     */
    public function send(string $phone, string $message): void;
}
