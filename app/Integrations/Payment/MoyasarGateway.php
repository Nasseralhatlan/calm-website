<?php

declare(strict_types=1);

namespace App\Integrations\Payment;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Minimal Moyasar Invoices client: create a hosted-payment invoice and fetch
 * its current state. Auth is HTTP basic with the secret key as the username and
 * a blank password (Moyasar's documented scheme). Money is always in halalas.
 */
final class MoyasarGateway
{
    private string $secretKey;

    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey = (string) config('moyasar.secret_key');
        $this->baseUrl = rtrim((string) config('moyasar.base_url', 'https://api.moyasar.com/v1/'), '/').'/';
    }

    /**
     * Create a hosted invoice and return its checkout URL + id.
     *
     * @param  int  $amountMinor  Amount to charge in halalas.
     * @param  array<string, scalar>  $metadata  Echoed back on status/webhook (carry booking_id here).
     * @param  CarbonImmutable|null  $expiredAt  When the hosted invoice stops accepting payment.
     *
     * @throws RuntimeException when Moyasar rejects the request.
     */
    public function createInvoice(int $amountMinor, string $description, string $callbackUrl, array $metadata = [], ?CarbonImmutable $expiredAt = null): MoyasarInvoice
    {
        $payload = [
            'amount' => $amountMinor,
            'currency' => 'SAR',
            'description' => $description,
            'callback_url' => $callbackUrl,
            'success_url' => (string) config('moyasar.success_url'),
            'back_url' => (string) config('moyasar.back_url'),
            'expired_at' => ($expiredAt ?? CarbonImmutable::now()->addMinutes((int) config('moyasar.hold_minutes', 10)))->toIso8601String(),
        ];

        foreach ($metadata as $key => $value) {
            $payload["metadata[{$key}]"] = $value;
        }

        $response = $this->client()->asForm()->post($this->baseUrl.'invoices', $payload);

        if (! $response->successful()) {
            Log::error('Moyasar invoice creation failed', ['status' => $response->status(), 'body' => $response->body()]);

            throw new RuntimeException('Moyasar invoice creation failed ('.$response->status().').');
        }

        return MoyasarInvoice::fromArray($response->json());
    }

    /**
     * Fetch an invoice's current state — the source of truth we re-verify
     * against before confirming a booking (never trust a webhook body alone).
     *
     * @throws RuntimeException on a non-2xx response.
     */
    public function fetchInvoice(string $invoiceId): MoyasarInvoice
    {
        $response = $this->client()->get($this->baseUrl."invoices/{$invoiceId}");

        if (! $response->successful()) {
            Log::error('Moyasar invoice fetch failed', ['invoice' => $invoiceId, 'status' => $response->status()]);

            throw new RuntimeException('Moyasar invoice fetch failed ('.$response->status().').');
        }

        return MoyasarInvoice::fromArray($response->json());
    }

    /**
     * Cancel an open invoice so it can no longer be paid — used when the guest
     * backs out of the hosted page. A paid/closed invoice can't be cancelled, so
     * callers treat a thrown error as "nothing to cancel".
     *
     * @throws RuntimeException on a non-2xx response.
     */
    public function cancelInvoice(string $invoiceId): MoyasarInvoice
    {
        $response = $this->client()->post($this->baseUrl."invoices/{$invoiceId}/cancel");

        if (! $response->successful()) {
            Log::warning('Moyasar invoice cancel failed', ['invoice' => $invoiceId, 'status' => $response->status()]);

            throw new RuntimeException('Moyasar invoice cancel failed ('.$response->status().').');
        }

        return MoyasarInvoice::fromArray($response->json());
    }

    /**
     * Refund the PAID payment underneath a hosted invoice, in full. Refunds
     * live on payments (not invoices), so this resolves the payment first.
     * Idempotent: a payment that already carries a refunded amount is left
     * alone, making a crash-and-retry cancellation safe.
     *
     * @return array<string, mixed> The (refunded or already-refunded) payment.
     *
     * @throws RuntimeException when no paid payment exists or Moyasar refuses.
     */
    public function refundInvoice(string $invoiceId): array
    {
        $response = $this->client()->get($this->baseUrl."invoices/{$invoiceId}");

        if (! $response->successful()) {
            Log::error('Moyasar invoice fetch for refund failed', ['invoice' => $invoiceId, 'status' => $response->status()]);

            throw new RuntimeException('Moyasar invoice fetch failed ('.$response->status().').');
        }

        $payment = collect($response->json('payments') ?? [])
            ->first(fn (array $p): bool => ($p['status'] ?? null) === 'paid' || (int) ($p['refunded'] ?? 0) > 0);

        if ($payment === null) {
            throw new RuntimeException('No paid payment found under invoice '.$invoiceId.'.');
        }

        if ((int) ($payment['refunded'] ?? 0) > 0) {
            return $payment; // already refunded — nothing to do
        }

        $refund = $this->client()->post($this->baseUrl."payments/{$payment['id']}/refund");

        if (! $refund->successful()) {
            Log::error('Moyasar refund failed', ['payment' => $payment['id'], 'status' => $refund->status(), 'body' => $refund->body()]);

            throw new RuntimeException('Moyasar refund failed ('.$refund->status().').');
        }

        return (array) $refund->json();
    }

    private function client(): PendingRequest
    {
        return Http::withBasicAuth($this->secretKey, '')
            ->acceptJson()
            ->timeout(20);
    }
}
