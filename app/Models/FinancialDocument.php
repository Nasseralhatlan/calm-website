<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An official financial document: invoice, credit note, or (internal)
 * settlement statement. Immutable once issued — corrections are always new
 * documents (credit notes), never edits. Tax documents sync to Qoyod, which
 * owns the ZATCA-compliant e-invoice; the external_* columns mirror it.
 */
class FinancialDocument extends Model
{
    use HasUuids;

    // document_type
    public const TYPE_INVOICE = 'invoice';

    public const TYPE_CREDIT_NOTE = 'credit_note';

    public const TYPE_DEBIT_NOTE = 'debit_note';

    public const TYPE_SETTLEMENT_STATEMENT = 'settlement_statement';

    public const TYPE_VOUCHER = 'voucher';

    // document_subtype (v1 set)
    public const GUEST_BOOKING_INVOICE = 'guest_booking_invoice';

    public const HOST_COMMISSION_INVOICE = 'host_commission_invoice';

    public const HOST_PAYOUT_STATEMENT = 'host_payout_statement';

    public const GUEST_BOOKING_CREDIT_NOTE = 'guest_booking_credit_note';

    public const HOST_COMMISSION_CREDIT_NOTE = 'host_commission_credit_note';

    // سند صرف: mirrors the settled bank transfer to the host into Qoyod, so
    // the Moyasar clearing account reconciles (money out = payout paid).
    public const HOST_PAYOUT_VOUCHER = 'host_payout_voucher';

    // سند صرف for a post-invoicing (Case C) refund: the money returned to the
    // guest leaves the Moyasar clearing account — without this the refunded
    // cash would sit on the Qoyod books forever.
    public const GUEST_REFUND_VOUCHER = 'guest_refund_voucher';

    // status
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_PROVIDER = 'pending_provider';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CREDITED = 'credited';

    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'source_type', 'source_id',
        'document_type', 'document_subtype',
        'seller_type', 'seller_id', 'buyer_type', 'buyer_id',
        'direction', 'status', 'is_tax_document', 'currency',
        'subtotal_amount', 'vat_amount', 'total_amount',
        'external_provider', 'external_contact_id', 'external_document_id',
        'external_document_number', 'external_uuid', 'external_pdf_url',
        'external_xml_url', 'external_qr', 'external_status',
        'external_payload', 'external_response',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'is_tax_document' => 'boolean',
            'subtotal_amount' => 'integer',
            'vat_amount' => 'integer',
            'total_amount' => 'integer',
            'external_payload' => 'array',
            'external_response' => 'array',
            'issued_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FinancialDocumentLine::class)->orderBy('created_at');
    }

    /** The business record this document belongs to (booking, later more). */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** The user being billed / addressed (guest or host). */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }
}
