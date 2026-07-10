<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A money movement (or deliberate withholding) — the bank-statement layer.
 * Not an invoice: invoices say what is owed, movements prove what moved.
 */
class FinancialMovement extends Model
{
    use HasUuids;

    public const GUEST_PAYMENT = 'guest_payment';

    public const COMMISSION_WITHHELD = 'commission_withheld';

    public const HOST_PAYOUT_PAYABLE = 'host_payout_payable';

    public const HOST_PAYOUT = 'host_payout';

    public const GUEST_REFUND = 'guest_refund';

    public const PAYMENT_PROVIDER_FEE = 'payment_provider_fee';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'source_type', 'source_id',
        'movement_type',
        'from_party_type', 'from_party_id',
        'to_party_type', 'to_party_id',
        'amount', 'currency',
        'provider', 'provider_transaction_id', 'provider_reference',
        'status', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
