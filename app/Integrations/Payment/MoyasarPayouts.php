<?php

declare(strict_types=1);

namespace App\Integrations\Payment;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Minimal Moyasar Payouts client: execute a bank transfer from the company's
 * registered payout account (Al Rajhi / SNB) to a host IBAN, and poll its
 * state. Same basic-auth scheme as MoyasarGateway; money is in halalas.
 *
 * Duplicate protection comes from the 16-digit `sequence_number`: Moyasar
 * refuses a second payout carrying the same number, so a crashed or
 * concurrently-retried run can never pay a host twice.
 */
final class MoyasarPayouts
{
    private string $secretKey;

    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey = (string) config('moyasar.secret_key');
        $this->baseUrl = rtrim((string) config('moyasar.base_url', 'https://api.moyasar.com/v1/'), '/').'/';
    }

    /**
     * Create a payout (bank transfer) and return Moyasar's payout object
     * (id, status: queued|initiated|paid|failed|canceled|returned, ...).
     *
     * @param  array<string, string>  $destination  {type: bank, iban, name, mobile?, country, city}
     * @return array<string, mixed>
     *
     * @throws RuntimeException when Moyasar rejects the request.
     */
    public function createPayout(int $amountMinor, array $destination, string $sequenceNumber, string $comment): array
    {
        $response = $this->client()->post($this->baseUrl.'payouts', [
            'source_id' => (string) config('moyasar.payout_account_id'),
            'amount' => $amountMinor,
            'currency' => 'SAR',
            'purpose' => (string) config('moyasar.payout_purpose', 'expenses_services'),
            'sequence_number' => $sequenceNumber,
            'comment' => $comment,
            'destination' => $destination,
        ]);

        if (! $response->successful()) {
            Log::error('Moyasar payout creation failed', ['status' => $response->status(), 'body' => $response->body()]);

            throw new RuntimeException('Moyasar payout creation failed ('.$response->status().'): '.mb_substr($response->body(), 0, 300));
        }

        return (array) $response->json();
    }

    /**
     * Current payout state — the source of truth the reconciler settles from.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException on a non-2xx response.
     */
    public function fetchPayout(string $payoutId): array
    {
        $response = $this->client()->get($this->baseUrl."payouts/{$payoutId}");

        if (! $response->successful()) {
            Log::error('Moyasar payout fetch failed', ['payout' => $payoutId, 'status' => $response->status()]);

            throw new RuntimeException('Moyasar payout fetch failed ('.$response->status().').');
        }

        return (array) $response->json();
    }

    /**
     * Deterministic 16-digit sequence_number for (booking, attempt): the same
     * attempt always maps to the same number, so a concurrent or crash-retried
     * create is deduplicated by Moyasar instead of paying twice. The attempt
     * counter only advances on a CONFIRMED bank failure, which consumed its
     * sequence without moving money — never on ambiguous (timeout) errors.
     */
    public function sequenceNumberFor(string $bookingId, int $attempt = 0): string
    {
        return sprintf(
            '%08d%08d',
            crc32('calm-payout:'.$bookingId) % 100_000_000,
            crc32($attempt.':'.$bookingId) % 100_000_000,
        );
    }

    private function client(): PendingRequest
    {
        return Http::withBasicAuth($this->secretKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }
}
