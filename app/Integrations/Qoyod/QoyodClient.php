<?php

declare(strict_types=1);

namespace App\Integrations\Qoyod;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin HTTP wrapper over the Qoyod REST API v2 (apidoc.qoyod.com).
 * Auth = per-business private key in the `API-KEY` header. All amounts cross
 * this boundary as SAR decimal strings — conversion from halalas happens in
 * the sync service, nowhere else.
 */
final class QoyodClient
{
    /** @param array<string, mixed> $contact */
    public function createCustomer(array $contact): array
    {
        return $this->post('/customers', ['contact' => $contact]);
    }

    /** @param array<string, mixed> $invoice */
    public function createInvoice(array $invoice): array
    {
        return $this->post('/invoices', ['invoice' => $invoice]);
    }

    /** @param array<string, mixed> $payment */
    public function createInvoicePayment(array $payment): array
    {
        return $this->post('/invoice_payments', ['invoice_payment' => $payment]);
    }

    /** @param array<string, mixed> $creditNote */
    public function createCreditNote(array $creditNote): array
    {
        return $this->post('/credit_notes', ['credit_note' => $creditNote]);
    }

    /**
     * Standalone receipt: kind `received` = سند قبض, kind `paid` = سند صرف
     * (money out — used to mirror settled host payouts).
     *
     * @param  array<string, mixed>  $receipt
     */
    public function createReceipt(array $receipt): array
    {
        return $this->post('/receipts', ['receipt' => $receipt]);
    }

    /** Fresh, EXPIRING pdf link for an invoice — fetched per view, never stored long-term. */
    public function invoicePdf(string $qoyodInvoiceId): array
    {
        $response = $this->client()->get("/invoices/{$qoyodInvoiceId}/pdf");

        if (! $response->successful()) {
            throw new RuntimeException('Qoyod pdf fetch failed with HTTP '.$response->status());
        }

        return (array) $response->json();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        $response = $this->client()->post($path, $payload);

        if (! $response->successful()) {
            Log::warning('qoyod: request failed', [
                'path' => $path,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            throw new RuntimeException('Qoyod '.$path.' failed with HTTP '.$response->status());
        }

        return (array) $response->json();
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders(['API-KEY' => (string) config('finance.qoyod.api_key')])
            ->acceptJson()
            ->baseUrl((string) config('finance.qoyod.base_url'))
            ->timeout((int) config('finance.qoyod.timeout', 30));
    }
}
