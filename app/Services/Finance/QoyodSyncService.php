<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Integrations\Qoyod\QoyodClient;
use App\Models\Booking;
use App\Models\FinancialDocument;
use App\Models\HostTaxProfile;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mirrors Calm's tax documents into Qoyod (the ZATCA-qualified system of
 * record): customers for guests and hosts, the two invoices per booking with
 * their payment records (Moyasar for the guest, withholding offset for the
 * commission), and credit notes for cancellations. The payout settlement
 * statement is internal and NEVER sent (brief §12).
 *
 * Documents needing sync sit at status pending_provider/failed; each sweep
 * retries them until issued, so a Qoyod outage only ever delays, never loses.
 */
final class QoyodSyncService
{
    public function __construct(private readonly QoyodClient $client) {}

    public function enabled(): bool
    {
        // filled() so an empty-string QOYOD_API_KEY counts as absent too —
        // MUST stay in lockstep with FinancialDocumentService's initial-status
        // gate, or documents are created pending_provider and never synced.
        return (bool) config('finance.qoyod.enabled') && filled(config('finance.qoyod.api_key'));
    }

    /**
     * The document subtypes that get mirrored into Qoyod: the two tax
     * invoices, their credit notes, and the host payout voucher (سند صرف).
     * The settlement statement stays internal.
     */
    private const SYNCED_SUBTYPES = [
        FinancialDocument::GUEST_BOOKING_INVOICE,
        FinancialDocument::HOST_COMMISSION_INVOICE,
        FinancialDocument::GUEST_BOOKING_CREDIT_NOTE,
        FinancialDocument::HOST_COMMISSION_CREDIT_NOTE,
        FinancialDocument::HOST_PAYOUT_VOUCHER,
        FinancialDocument::GUEST_REFUND_VOUCHER,
    ];

    /** Push every pending/failed syncable document. Called by the finance sweep. */
    public function syncPendingDocuments(): void
    {
        if (! $this->enabled()) {
            return;
        }

        FinancialDocument::query()
            ->whereIn('document_subtype', self::SYNCED_SUBTYPES)
            ->whereIn('status', [FinancialDocument::STATUS_PENDING_PROVIDER, FinancialDocument::STATUS_FAILED])
            ->where('source_type', 'booking')
            ->with('source')
            ->chunkById(25, function ($documents): void {
                foreach ($documents as $document) {
                    try {
                        $this->syncDocument($document);
                    } catch (Throwable $e) {
                        $document->update([
                            'status' => FinancialDocument::STATUS_FAILED,
                            'external_status' => mb_substr($e->getMessage(), 0, 100),
                        ]);
                        Log::warning('qoyod: document sync failed', [
                            'document' => $document->id,
                            'subtype' => $document->document_subtype,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
    }

    private function syncDocument(FinancialDocument $document): void
    {
        /** @var Booking|null $booking */
        $booking = $document->source;
        if ($booking === null) {
            return;
        }

        match ($document->document_subtype) {
            FinancialDocument::GUEST_BOOKING_INVOICE => $this->syncGuestInvoice($booking, $document),
            FinancialDocument::HOST_COMMISSION_INVOICE => $this->syncCommissionInvoice($booking, $document),
            FinancialDocument::GUEST_BOOKING_CREDIT_NOTE,
            FinancialDocument::HOST_COMMISSION_CREDIT_NOTE => $this->syncCreditNote($booking, $document),
            FinancialDocument::HOST_PAYOUT_VOUCHER => $this->syncPayoutVoucher($booking, $document),
            FinancialDocument::GUEST_REFUND_VOUCHER => $this->syncRefundVoucher($booking, $document),
            default => null,
        };
    }

    /**
     * سند صرف: a kind=paid receipt out of the Moyasar clearing account to the
     * host contact, matching the settled bank transfer — so the account's
     * balance in Qoyod reconciles against the real Moyasar balance.
     */
    private function syncPayoutVoucher(Booking $booking, FinancialDocument $document): void
    {
        $contactId = $this->ensureHostCustomer($booking, HostTaxProfile::query()->where('host_user_id', $booking->host_user_id)->first());

        $this->pushPaidReceipt(
            $document,
            $contactId,
            "{$booking->reference}-PAYOUT",
            "Calm host payout {$booking->reference}"
                .($booking->payout_reference ? " — bank ref {$booking->payout_reference}" : ''),
        );
    }

    /**
     * سند صرف for a Case C refund: the money returned to the guest leaves the
     * Moyasar clearing account — mirrors the Moyasar refund so the account
     * still reconciles after an invoiced-then-cancelled booking.
     */
    private function syncRefundVoucher(Booking $booking, FinancialDocument $document): void
    {
        $contactId = $this->ensureGuestCustomer($booking->guest);

        $this->pushPaidReceipt(
            $document,
            $contactId,
            "{$booking->reference}-REFUND",
            "Calm refund to guest for booking {$booking->reference}"
                .($booking->payment_id ? " — Moyasar {$booking->payment_id}" : ''),
        );
    }

    /**
     * Shared money-out push: a kind=paid receipt from the Moyasar clearing
     * account for the document's total. Resume-safe (never creates a second
     * receipt for a document that already carries a Qoyod id) and fails
     * loudly on a response with no id, same rules as invoices.
     */
    private function pushPaidReceipt(FinancialDocument $document, string $contactId, string $reference, string $description): void
    {
        if ($document->external_document_id === null) {
            $payload = [
                'contact_id' => (int) $contactId,
                'reference' => $reference,
                'kind' => 'paid',
                'account_id' => (int) config('finance.qoyod.moyasar_account_id'),
                'amount' => $this->sar((int) $document->total_amount),
                'description' => $description,
                'date' => now()->toDateString(),
            ];

            $response = $this->client->createReceipt($payload);
            $receipt = (array) ($response['receipt'] ?? $response);

            $document->update([
                'external_provider' => 'qoyod',
                'external_contact_id' => $contactId,
                'external_document_id' => isset($receipt['id']) ? (string) $receipt['id'] : null,
                'external_document_number' => $receipt['reference'] ?? $reference,
                'external_payload' => $payload,
                'external_response' => $response,
                'external_status' => 'created',
            ]);

            if ($document->external_document_id === null) {
                throw new \RuntimeException("Qoyod created voucher {$reference} but returned no id — check the Qoyod books before retrying.");
            }
        }

        $document->update(['status' => FinancialDocument::STATUS_ISSUED]);
    }

    private function syncGuestInvoice(Booking $booking, FinancialDocument $document): void
    {
        $contactId = $this->ensureGuestCustomer($booking->guest);

        $lineItems = [[
            'product_id' => (int) config('finance.qoyod.product_stay_id'),
            'description' => "Accommodation booking {$booking->reference}",
            'quantity' => 1,
            'unit_price' => $this->sar((int) $booking->stay_amount),
            'tax_percent' => (float) $booking->vat_rate,
        ]];

        $this->pushInvoice($document, $contactId, "{$booking->reference}-G", $lineItems, $booking, [
            'account_id' => (int) config('finance.qoyod.moyasar_account_id'),
            'reference' => (string) ($booking->payment_id ?? $booking->reference),
        ]);
    }

    private function syncCommissionInvoice(Booking $booking, FinancialDocument $document): void
    {
        $profile = HostTaxProfile::query()->where('host_user_id', $booking->host_user_id)->first();
        $contactId = $this->ensureHostCustomer($booking, $profile);

        $lineItems = [[
            'product_id' => (int) config('finance.qoyod.product_commission_id'),
            'description' => "Platform commission for booking {$booking->reference}",
            'quantity' => 1,
            'unit_price' => $this->sar((int) $booking->commission_amount),
            'tax_percent' => (float) $booking->commission_vat_rate,
        ]];

        $this->pushInvoice($document, $contactId, "{$booking->reference}-C", $lineItems, $booking, [
            'account_id' => (int) config('finance.qoyod.settlement_account_id'),
            'reference' => $booking->reference.'-OFFSET',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $lineItems
     * @param  array{account_id: int, reference: string}  $payment
     */
    private function pushInvoice(FinancialDocument $document, string $contactId, string $reference, array $lineItems, Booking $booking, array $payment): void
    {
        // Resume-safe: Qoyod invoice references are UNIQUE, so if a previous
        // attempt created the invoice but died on the payment call, we must
        // NOT create it again — pick up at the payment step instead.
        if ($document->external_document_id === null) {
            // No custom_fields on purpose: Qoyod requires them to be
            // pre-configured in the org settings; the booking linkage lives
            // in `reference` + `description` instead.
            $payload = [
                'contact_id' => (int) $contactId,
                'reference' => $reference,
                'description' => "Calm booking {$booking->reference}",
                'issue_date' => ($document->issued_at ?? now())->toDateString(),
                'due_date' => ($document->issued_at ?? now())->toDateString(),
                'status' => 'Approved',
                'inventory_id' => (int) config('finance.qoyod.inventory_id', 1),
                'line_items' => $lineItems,
            ];

            $response = $this->client->createInvoice($payload);
            $invoice = (array) ($response['invoice'] ?? $response);

            $document->update([
                'external_provider' => 'qoyod',
                'external_contact_id' => $contactId,
                'external_document_id' => isset($invoice['id']) ? (string) $invoice['id'] : null,
                'external_document_number' => $invoice['reference'] ?? $reference,
                'external_payload' => $payload,
                'external_response' => $response,
                'external_status' => 'created',
            ]);

            // A 2xx without an id means the invoice may exist in Qoyod with no
            // local link. Fail loudly (doc goes `failed`, response is stored
            // above for forensics) instead of flipping to issued with no
            // receipt and silently leaving the retry set.
            if ($document->external_document_id === null) {
                throw new \RuntimeException("Qoyod created invoice {$reference} but returned no id — check the Qoyod books before retrying.");
            }
        }

        // Both invoices are already settled in reality (guest paid via
        // Moyasar; commission withheld) — record the payment in Qoyod too.
        if ($document->external_document_id !== null) {
            $this->client->createInvoicePayment([
                'reference' => $payment['reference'],
                'invoice_id' => (string) $document->external_document_id,
                'account_id' => (string) $payment['account_id'],
                'date' => now()->toDateString(),
                'amount' => $this->sar((int) $document->total_amount),
            ]);
        }

        $document->update(['status' => FinancialDocument::STATUS_ISSUED]);
    }

    private function syncCreditNote(Booking $booking, FinancialDocument $document): void
    {
        // Resume-safe, same rule as pushInvoice: if a previous attempt created
        // the note in Qoyod but died before the local update, do NOT create a
        // duplicate — just finish flipping the status.
        if ($document->external_document_id === null) {
            $isGuest = $document->document_subtype === FinancialDocument::GUEST_BOOKING_CREDIT_NOTE;
            $contactId = $isGuest
                ? $this->ensureGuestCustomer($booking->guest)
                : $this->ensureHostCustomer($booking, HostTaxProfile::query()->where('host_user_id', $booking->host_user_id)->first());

            $response = $this->client->createCreditNote([
                'contact_id' => (int) $contactId,
                'issue_date' => now()->toDateString(),
                'status' => 'Approved',
                'inventory_id' => (string) config('finance.qoyod.inventory_id', 1),
                'notes' => "Reversal for booking {$booking->reference}",
                'line_items' => [[
                    'product_id' => (int) ($isGuest ? config('finance.qoyod.product_stay_id') : config('finance.qoyod.product_commission_id')),
                    'description' => ($isGuest ? 'Refund' : 'Commission reversal')." — booking {$booking->reference}",
                    'quantity' => '1.0',
                    'unit_price' => $this->sar((int) $document->subtotal_amount),
                    'tax_percent' => $isGuest ? (string) $booking->vat_rate : (string) $booking->commission_vat_rate,
                ]],
            ]);

            $document->update([
                'external_provider' => 'qoyod',
                'external_contact_id' => $contactId,
                'external_document_id' => isset($response['id']) ? (string) $response['id'] : null,
                'external_document_number' => $response['note_no'] ?? null,
                'external_response' => $response,
                'external_status' => 'created',
            ]);

            // Same no-id rule as pushInvoice: never mark issued on a response
            // we can't link back to.
            if ($document->external_document_id === null) {
                throw new \RuntimeException("Qoyod created a credit note for booking {$booking->reference} but returned no id — check the Qoyod books before retrying.");
            }
        }

        $document->update(['status' => FinancialDocument::STATUS_ISSUED]);
    }

    /** Fresh expiring PDF link for a synced tax document (Qoyod-hosted). */
    public function pdfUrl(FinancialDocument $document): ?string
    {
        // Statements are internal; payout vouchers are Qoyod receipts, which
        // have no invoice-PDF endpoint — neither ever links out.
        if (
            ! $this->enabled()
            || $document->external_document_id === null
            || in_array($document->document_type, [FinancialDocument::TYPE_SETTLEMENT_STATEMENT, FinancialDocument::TYPE_VOUCHER], true)
        ) {
            return null;
        }

        $response = $this->client->invoicePdf($document->external_document_id);

        return isset($response['pdf_file']) ? (string) $response['pdf_file'] : null;
    }

    private function ensureGuestCustomer(?User $guest): string
    {
        if ($guest === null) {
            throw new \RuntimeException('Booking has no guest.');
        }

        if ($guest->qoyod_customer_id !== null) {
            return (string) $guest->qoyod_customer_id;
        }

        // Only `name` is required; empty optional fields are OMITTED (an
        // empty-string email would trip Qoyod's format validation).
        $response = $this->client->createCustomer(array_filter([
            'name' => (string) ($guest->name ?: 'Guest '.$guest->phone),
            'phone_number' => (string) $guest->phone,
            'email' => (string) ($guest->email ?? ''),
            'status' => 'Active',
        ], fn ($value) => $value !== ''));

        $id = (string) ((array) ($response['contact'] ?? []))['id'];
        $guest->forceFill(['qoyod_customer_id' => $id])->save();

        return $id;
    }

    private function ensureHostCustomer(Booking $booking, ?HostTaxProfile $profile): string
    {
        if ($profile?->qoyod_customer_id !== null) {
            return (string) $profile->qoyod_customer_id;
        }

        $host = $booking->host;

        // The finalizer normally creates the profile first, but this sync path
        // must not depend on that ordering: without a row to persist onto, the
        // Qoyod contact id would be discarded and every sync would create a
        // fresh duplicate customer. (Same defaults as the finalizer's
        // ensureHostTaxProfile.)
        $profile ??= HostTaxProfile::query()->firstOrCreate(
            ['host_user_id' => $booking->host_user_id],
            [
                'host_type' => 'individual',
                'legal_name' => (string) ($host?->name ?: 'Host '.$booking->host_user_id),
            ],
        );

        // firstOrCreate may have found a row that already carries a contact id.
        if ($profile->qoyod_customer_id !== null) {
            return (string) $profile->qoyod_customer_id;
        }

        $response = $this->client->createCustomer(array_filter([
            'name' => (string) ($profile->legal_name ?: $host?->name ?: 'Host '.$booking->host_user_id),
            'phone_number' => (string) ($host?->phone ?? ''),
            'email' => (string) ($host?->email ?? ''),
            'tax_number' => (string) ($profile->vat_number ?? ''),
            'status' => 'Active',
        ], fn ($value) => $value !== ''));

        $id = (string) ((array) ($response['contact'] ?? []))['id'];
        $profile->update(['qoyod_customer_id' => $id]);

        return $id;
    }

    /** Halalas → SAR decimal string; the ONLY place this conversion happens. */
    private function sar(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }
}
