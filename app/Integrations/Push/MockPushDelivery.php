<?php

declare(strict_types=1);

namespace App\Integrations\Push;

use App\Contracts\PushDeliveryContract;
use Illuminate\Support\Facades\Log;

final class MockPushDelivery implements PushDeliveryContract
{
    public function send(array $tokens, string $title, string $body, array $data = []): void
    {
        Log::info('[PUSH MOCK] dispatched', [
            'tokens' => $tokens,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }
}
