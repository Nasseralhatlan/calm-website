<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Booking;
use App\Models\FinancialDocument;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Creates the LOCAL financial document rows + lines (brief §13). One document
 * per (source, subtype) — enforced here by an existence check and at the DB by
 * the unique index, so double-issuing is impossible. Documents are immutable
 * once created; corrections are new credit-note documents.
 *
 * Qoyod syncing happens in a later step: tax documents start as
 * `pending_provider` when Qoyod is enabled (the sync flips them to `issued`),
 * or go straight to `issued` locally when it is disabled.
 */
final class FinancialDocumentService
{
    /** Calm → Guest simplified tax invoice (brief §13.1). */
    public function guestBookingInvoice(Booking $booking): FinancialDocument
    {
        return $this->idempotent($booking, FinancialDocument::GUEST_BOOKING_INVOICE, function () use ($booking): FinancialDocument {
            $document = FinancialDocument::query()->create([
                'source_type' => 'booking',
                'source_id' => $booking->id,
                'document_type' => FinancialDocument::TYPE_INVOICE,
                'document_subtype' => FinancialDocument::GUEST_BOOKING_INVOICE,
                'seller_type' => 'calm',
                'buyer_type' => 'guest',
                'buyer_id' => $booking->guest_user_id,
                'direction' => 'sales',
                'status' => $this->initialProviderStatus(),
                'is_tax_document' => true,
                'subtotal_amount' => (int) $booking->stay_amount,
                'vat_amount' => (int) $booking->vat_amount,
                'total_amount' => (int) $booking->total_amount,
                'issued_at' => now(),
            ]);

            $document->lines()->create([
                'description' => "Accommodation booking {$booking->reference} ({$booking->nights} nights) · حجز رقم {$booking->reference}",
                'quantity' => 1,
                'unit_amount' => (int) $booking->stay_amount,
                'subtotal_amount' => (int) $booking->stay_amount,
                'vat_rate' => (float) $booking->vat_rate,
                'vat_amount' => (int) $booking->vat_amount,
                'total_amount' => (int) $booking->total_amount,
                'source_type' => 'booking',
                'source_id' => $booking->id,
            ]);

            return $document;
        });
    }

    /** Calm → Host commission tax invoice, paid by withholding (brief §13.2). */
    public function hostCommissionInvoice(Booking $booking): FinancialDocument
    {
        return $this->idempotent($booking, FinancialDocument::HOST_COMMISSION_INVOICE, function () use ($booking): FinancialDocument {
            $document = FinancialDocument::query()->create([
                'source_type' => 'booking',
                'source_id' => $booking->id,
                'document_type' => FinancialDocument::TYPE_INVOICE,
                'document_subtype' => FinancialDocument::HOST_COMMISSION_INVOICE,
                'seller_type' => 'calm',
                'buyer_type' => 'host',
                'buyer_id' => $booking->host_user_id,
                'direction' => 'sales',
                'status' => $this->initialProviderStatus(),
                'is_tax_document' => true,
                'subtotal_amount' => (int) $booking->commission_amount,
                'vat_amount' => (int) $booking->commission_vat_amount,
                'total_amount' => (int) $booking->commission_total,
                'issued_at' => now(),
            ]);

            $document->lines()->create([
                'description' => "Platform commission for booking {$booking->reference} · عمولة حجز رقم {$booking->reference}",
                'quantity' => 1,
                'unit_amount' => (int) $booking->commission_amount,
                'subtotal_amount' => (int) $booking->commission_amount,
                'vat_rate' => (float) $booking->commission_vat_rate,
                'vat_amount' => (int) $booking->commission_vat_amount,
                'total_amount' => (int) $booking->commission_total,
                'source_type' => 'booking',
                'source_id' => $booking->id,
            ]);

            return $document;
        });
    }

    /** Internal host settlement statement — NEVER a tax invoice (brief §13.3). */
    public function hostPayoutStatement(Booking $booking): FinancialDocument
    {
        return $this->idempotent($booking, FinancialDocument::HOST_PAYOUT_STATEMENT, function () use ($booking): FinancialDocument {
            $document = FinancialDocument::query()->create([
                'source_type' => 'booking',
                'source_id' => $booking->id,
                'document_type' => FinancialDocument::TYPE_SETTLEMENT_STATEMENT,
                'document_subtype' => FinancialDocument::HOST_PAYOUT_STATEMENT,
                'seller_type' => 'calm',
                'buyer_type' => 'host',
                'buyer_id' => $booking->host_user_id,
                'direction' => 'internal',
                'status' => FinancialDocument::STATUS_ISSUED,
                'is_tax_document' => false,
                'subtotal_amount' => (int) $booking->stay_amount,
                'vat_amount' => 0,
                'total_amount' => $booking->hostNetMinor(),
                'issued_at' => now(),
            ]);

            // Statement lines mirror the بيان المستحق layout: value, then the
            // two deductions, with the document total = the payable amount.
            $document->lines()->createMany([
                [
                    'description' => "Booking value {$booking->reference} · إجمالي قيمة الحجز",
                    'quantity' => 1,
                    'unit_amount' => (int) $booking->stay_amount,
                    'subtotal_amount' => (int) $booking->stay_amount,
                    'total_amount' => (int) $booking->stay_amount,
                ],
                [
                    'description' => 'Commission (deduction) · العمولة',
                    'quantity' => 1,
                    'unit_amount' => (int) $booking->commission_amount,
                    'subtotal_amount' => (int) $booking->commission_amount,
                    'total_amount' => (int) $booking->commission_amount,
                ],
                [
                    'description' => 'Commission VAT (deduction) · ضريبة العمولة',
                    'quantity' => 1,
                    'unit_amount' => (int) $booking->commission_vat_amount,
                    'subtotal_amount' => (int) $booking->commission_vat_amount,
                    'total_amount' => (int) $booking->commission_vat_amount,
                ],
            ]);

            return $document;
        });
    }

    /**
     * سند صرف — mirrors the SETTLED bank transfer to the host into Qoyod, so
     * the Moyasar clearing account reconciles (guest money in, host share
     * out). Created only after the payout is confirmed paid; pushed by the
     * sync sweep as a kind=paid receipt. Not a tax document.
     */
    public function hostPayoutVoucher(Booking $booking): FinancialDocument
    {
        return $this->idempotent($booking, FinancialDocument::HOST_PAYOUT_VOUCHER, function () use ($booking): FinancialDocument {
            return FinancialDocument::query()->create([
                'source_type' => 'booking',
                'source_id' => $booking->id,
                'document_type' => FinancialDocument::TYPE_VOUCHER,
                'document_subtype' => FinancialDocument::HOST_PAYOUT_VOUCHER,
                'seller_type' => 'calm',
                'buyer_type' => 'host',
                'buyer_id' => $booking->host_user_id,
                'direction' => 'outbound',
                'status' => $this->initialProviderStatus(),
                'is_tax_document' => false,
                'subtotal_amount' => $booking->hostNetMinor(),
                'vat_amount' => 0,
                'total_amount' => $booking->hostNetMinor(),
                'issued_at' => now(),
            ]);
        });
    }

    /**
     * سند صرف for a Case C refund: the cash going back to the guest out of
     * the Moyasar clearing account. Only minted when tax documents existed
     * (post-invoicing) — a Case B refund has no Qoyod footprint to balance.
     */
    public function guestRefundVoucher(Booking $booking, int $amountMinor): FinancialDocument
    {
        return $this->idempotent($booking, FinancialDocument::GUEST_REFUND_VOUCHER, function () use ($booking, $amountMinor): FinancialDocument {
            return FinancialDocument::query()->create([
                'source_type' => 'booking',
                'source_id' => $booking->id,
                'document_type' => FinancialDocument::TYPE_VOUCHER,
                'document_subtype' => FinancialDocument::GUEST_REFUND_VOUCHER,
                'seller_type' => 'calm',
                'buyer_type' => 'guest',
                'buyer_id' => $booking->guest_user_id,
                'direction' => 'outbound',
                'status' => $this->initialProviderStatus(),
                'is_tax_document' => false,
                'subtotal_amount' => $amountMinor,
                'vat_amount' => 0,
                'total_amount' => $amountMinor,
                'issued_at' => now(),
            ]);
        });
    }

    /** Credit note against the guest invoice (Case C refunds, brief §14). */
    public function guestBookingCreditNote(Booking $booking, int $amountMinor): FinancialDocument
    {
        return $this->idempotent($booking, FinancialDocument::GUEST_BOOKING_CREDIT_NOTE, function () use ($booking, $amountMinor): FinancialDocument {
            $document = FinancialDocument::query()->create([
                'source_type' => 'booking',
                'source_id' => $booking->id,
                'document_type' => FinancialDocument::TYPE_CREDIT_NOTE,
                'document_subtype' => FinancialDocument::GUEST_BOOKING_CREDIT_NOTE,
                'seller_type' => 'calm',
                'buyer_type' => 'guest',
                'buyer_id' => $booking->guest_user_id,
                'direction' => 'sales',
                'status' => $this->initialProviderStatus(),
                'is_tax_document' => true,
                'subtotal_amount' => $amountMinor - $this->vatPortion($amountMinor, (float) $booking->vat_rate),
                'vat_amount' => $this->vatPortion($amountMinor, (float) $booking->vat_rate),
                'total_amount' => $amountMinor,
                'issued_at' => now(),
            ]);

            $document->lines()->create([
                'description' => "Refund for booking {$booking->reference} · استرجاع حجز رقم {$booking->reference}",
                'quantity' => 1,
                'unit_amount' => $document->subtotal_amount,
                'subtotal_amount' => $document->subtotal_amount,
                'vat_rate' => (float) $booking->vat_rate,
                'vat_amount' => $document->vat_amount,
                'total_amount' => $amountMinor,
                'source_type' => 'booking',
                'source_id' => $booking->id,
            ]);

            // The original invoice is now (fully or partially) credited.
            $booking->financialDocuments()
                ->where('document_subtype', FinancialDocument::GUEST_BOOKING_INVOICE)
                ->update(['status' => FinancialDocument::STATUS_CREDITED]);

            return $document;
        });
    }

    /** Credit note reversing the commission invoice (Case C, brief §14). */
    public function hostCommissionCreditNote(Booking $booking): FinancialDocument
    {
        return $this->idempotent($booking, FinancialDocument::HOST_COMMISSION_CREDIT_NOTE, function () use ($booking): FinancialDocument {
            $document = FinancialDocument::query()->create([
                'source_type' => 'booking',
                'source_id' => $booking->id,
                'document_type' => FinancialDocument::TYPE_CREDIT_NOTE,
                'document_subtype' => FinancialDocument::HOST_COMMISSION_CREDIT_NOTE,
                'seller_type' => 'calm',
                'buyer_type' => 'host',
                'buyer_id' => $booking->host_user_id,
                'direction' => 'sales',
                'status' => $this->initialProviderStatus(),
                'is_tax_document' => true,
                'subtotal_amount' => (int) $booking->commission_amount,
                'vat_amount' => (int) $booking->commission_vat_amount,
                'total_amount' => (int) $booking->commission_total,
                'issued_at' => now(),
            ]);

            $document->lines()->create([
                'description' => "Commission reversal for booking {$booking->reference} · عكس عمولة حجز رقم {$booking->reference}",
                'quantity' => 1,
                'unit_amount' => (int) $booking->commission_amount,
                'subtotal_amount' => (int) $booking->commission_amount,
                'vat_rate' => (float) $booking->commission_vat_rate,
                'vat_amount' => (int) $booking->commission_vat_amount,
                'total_amount' => (int) $booking->commission_total,
            ]);

            $booking->financialDocuments()
                ->where('document_subtype', FinancialDocument::HOST_COMMISSION_INVOICE)
                ->update(['status' => FinancialDocument::STATUS_CREDITED]);

            return $document;
        });
    }

    /**
     * The viewer's own documents for the mobile "My documents" list — guests
     * see their booking invoices/credit notes, hosts their commission
     * invoices + payout statements. Newest first, paginated.
     *
     * @return LengthAwarePaginator<int, FinancialDocument>
     */
    public function forUser(User $user, ?string $bookingId = null, ?int $perPage = null): LengthAwarePaginator
    {
        return FinancialDocument::query()
            ->where('buyer_id', $user->id)
            // Users see only documents that MEAN something to them: invoices,
            // credit notes, statements. Vouchers (سند صرف/قبض) are internal
            // Qoyod cash mirrors — the credit note / payout statement already
            // tell the human story, so vouchers never reach the mobile list.
            ->where('document_type', '!=', FinancialDocument::TYPE_VOUCHER)
            // Booking scoping stays INSIDE the buyer scope: someone else's
            // booking id yields an empty list, never leaking existence.
            ->when($bookingId !== null, fn ($query) => $query
                ->where('source_type', 'booking')
                ->where('source_id', $bookingId))
            ->with('source')
            ->latest('issued_at')
            ->paginate($perPage ?? config('pagination.per_page'))
            ->withQueryString();
    }

    /** Return-the-existing-or-create — the (source, subtype) idempotency rule. */
    private function idempotent(Booking $booking, string $subtype, callable $create): FinancialDocument
    {
        $existing = FinancialDocument::query()
            ->where('source_type', 'booking')
            ->where('source_id', $booking->id)
            ->where('document_subtype', $subtype)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction($create);
    }

    private function initialProviderStatus(): string
    {
        // Mirror QoyodSyncService::enabled() EXACTLY (flag + a non-empty API
        // key): enabled-but-keyless would otherwise create documents as
        // pending_provider that the sync (correctly) never picks up — stuck
        // forever with no PDF.
        return config('finance.qoyod.enabled') && filled(config('finance.qoyod.api_key'))
            ? FinancialDocument::STATUS_PENDING_PROVIDER
            : FinancialDocument::STATUS_ISSUED;
    }

    /** VAT share inside a VAT-inclusive amount at the given rate. */
    private function vatPortion(int $inclusiveMinor, float $rate): int
    {
        if ($rate <= 0) {
            return 0;
        }

        return (int) round($inclusiveMinor * $rate / (100 + $rate));
    }
}
