<?php

declare(strict_types=1);

namespace App\Integrations\Sms;

use App\Contracts\SmsDeliveryContract;
use Illuminate\Support\Facades\Log;

final class MockSmsDelivery implements SmsDeliveryContract
{
    public function send(string $phone, string $message): void
    {
        Log::info('[SMS MOCK] dispatched', [
            'phone' => $phone,
            'message' => $message,
        ]);
    }
}
