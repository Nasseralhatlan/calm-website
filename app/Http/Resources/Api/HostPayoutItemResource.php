<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the host Finance tab's "Transfers" ledger — carries everything
 * the inline expansion needs: gross − commission = net, the payout state,
 * and when the money moved (or is expected to). HOST-side money only; the
 * guest's totals stay in BookingResource.
 *
 *   payout_state: paid | processing | upcoming | awaiting_completion
 *                 | awaiting_bank_details | failed
 *   expected_at:  when the payout unlocks (checkout + hold) — null once paid.
 */
class HostPayoutItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $state = $this->payoutState();

        return [
            'booking_id' => $this->id,
            'booking_reference' => $this->reference,
            'place_title' => $this->place?->title,
            'check_in' => $this->start_date?->toDateString(),
            'check_out' => $this->end_date?->toDateString(),
            'currency' => 'SAR',
            'gross' => $this->stay_amount / 100,
            'gross_minor' => (int) $this->stay_amount,
            'commission' => $this->commission_total / 100,
            'commission_minor' => (int) $this->commission_total,
            'net' => $this->host_payout_amount / 100,
            'net_minor' => (int) $this->host_payout_amount,
            'payout_state' => $state,
            'payout_paid_at' => $this->payout_paid_at?->toIso8601String(),
            'payout_reference' => $this->payout_reference,
            'expected_at' => $state === 'paid' ? null : $this->payableAt()?->toIso8601String(),
            // The host's paper for this booking, embedded so opening a row
            // needs no extra request (and rows can badge "invoice available").
            // The expiring PDF link is still minted per tap via
            // GET /finance-documents/{id}/pdf-url.
            'documents' => $this->financialDocuments->map(fn ($document): array => [
                'id' => $document->id,
                'document_type' => $document->document_type,
                'number' => $document->external_document_number,
                'total_amount' => (int) $document->total_amount,
                'has_pdf' => $document->external_document_id !== null,
                'issued_at' => $document->issued_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
