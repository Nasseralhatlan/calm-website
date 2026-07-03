<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tax identity of a host — who Calm's commission invoice is issued to, and
 * their Qoyod customer id. Auto-created minimal (individual, legal name =
 * account name) on first finance finalization; enriched by admin for
 * companies (CR number, VAT registration).
 */
class HostTaxProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'host_user_id',
        'host_type', 'legal_name',
        'commercial_registration_number',
        'vat_registered', 'vat_number', 'vat_registration_date',
        'qoyod_customer_id', 'qoyod_vendor_id',
    ];

    protected function casts(): array
    {
        return [
            'vat_registered' => 'boolean',
            'vat_registration_date' => 'date',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }
}
