<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\Booking;
use App\Models\FinancialDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A financial document as the mobile app lists it: what it is, its money
 * summary, and whether a (Qoyod-hosted, expiring) PDF can be fetched via the
 * pdf-url endpoint. Amounts in halalas, like every money field in the API.
 *
 * @mixin FinancialDocument
 */
class FinanceDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $booking = $this->source instanceof Booking ? $this->source : null;

        return [
            'id' => $this->id,
            'document_type' => $this->document_type,
            'document_subtype' => $this->document_subtype,
            'status' => $this->status,
            'is_tax_document' => $this->is_tax_document,
            'number' => $this->external_document_number,
            'currency' => $this->currency,
            'subtotal_amount' => $this->subtotal_amount,
            'vat_amount' => $this->vat_amount,
            'total_amount' => $this->total_amount,
            'booking_reference' => $booking?->reference,
            'issued_at' => $this->issued_at?->toIso8601String(),
            // Only Qoyod-synced tax documents have a fetchable PDF.
            'has_pdf' => $this->external_document_id !== null,
        ];
    }
}
