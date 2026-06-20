<?php

declare(strict_types=1);

namespace App\Integrations\Push;

use App\Contracts\PushDeliveryContract;
use App\Models\DeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Push delivery via Expo's push service.
 *
 *   POST https://exp.host/--/api/v2/push/send
 *   Body: [{ "to": "ExponentPushToken[..]", "title": "...", "body": "...",
 *            "data": {...}, "sound": "default" }, ...]   (max 100 per call)
 *
 * Expo returns a `data` array of tickets in the same order. A ticket with
 * status "error" and details.error == "DeviceNotRegistered" means the token is
 * dead — we prune it so we stop wasting calls on it. We never throw: push is a
 * best-effort channel and must not break the rest of a notification fan-out.
 */
final class ExpoPushDelivery implements PushDeliveryContract
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    private const BATCH = 100;

    public function __construct(
        private readonly ?string $accessToken = null,
        private readonly int $timeout = 10,
    ) {}

    public function send(array $tokens, string $title, string $body, array $data = []): void
    {
        $tokens = array_values(array_unique(array_filter($tokens)));

        foreach (array_chunk($tokens, self::BATCH) as $batch) {
            $this->sendBatch($batch, $title, $body, $data);
        }
    }

    /**
     * @param  list<string>  $tokens
     * @param  array<string, mixed>  $data
     */
    private function sendBatch(array $tokens, string $title, string $body, array $data): void
    {
        $messages = array_map(fn (string $token): array => [
            'to' => $token,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'sound' => 'default',
        ], $tokens);

        try {
            $request = Http::timeout($this->timeout)->acceptJson();
            if ($this->accessToken !== null && $this->accessToken !== '') {
                $request = $request->withToken($this->accessToken);
            }

            $response = $request->post(self::ENDPOINT, $messages);
        } catch (Throwable $e) {
            Log::warning('[PUSH] Expo request failed', ['error' => $e->getMessage()]);

            return;
        }

        $tickets = $response->json('data');
        if (! is_array($tickets)) {
            Log::warning('[PUSH] Expo unexpected response', ['body' => $response->body()]);

            return;
        }

        // Prune tokens Expo says are no longer valid, by position.
        foreach ($tickets as $i => $ticket) {
            $error = $ticket['details']['error'] ?? null;
            if (($ticket['status'] ?? null) === 'error'
                && in_array($error, ['DeviceNotRegistered', 'InvalidCredentials'], true)
                && isset($tokens[$i])) {
                DeviceToken::query()->where('token', $tokens[$i])->delete();
            }
        }
    }
}
