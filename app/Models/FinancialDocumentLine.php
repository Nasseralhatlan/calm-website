<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One printable line of a financial document. */
class FinancialDocumentLine extends Model
{
    use HasUuids;

    protected $fillable = [
        'financial_document_id',
        'description', 'quantity',
        'unit_amount', 'subtotal_amount',
        'vat_rate', 'vat_amount', 'total_amount',
        'source_type', 'source_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_amount' => 'integer',
            'subtotal_amount' => 'integer',
            'vat_rate' => 'float',
            'vat_amount' => 'integer',
            'total_amount' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(FinancialDocument::class, 'financial_document_id');
    }
}
