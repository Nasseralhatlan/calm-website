<?php

declare(strict_types=1);

namespace App\Services\Finance;

/**
 * Calculates and freezes the full booking money snapshot (brief §2):
 *
 *   stay_amount      what belongs to the host before commission
 *   guest_* fields         the Calm → Guest invoice numbers
 *   commission_* fields    the Calm → Host invoice numbers (VAT ON TOP)
 *   host_payout_amount     host_gross − commission_total
 *
 * Runs once at booking creation (also as a model `creating` safety net for
 * seeders/tests) and is never recomputed afterwards — the snapshot IS the
 * agreement.
 */
final class BookingPricingService
{
    /**
     * Complete the derived snapshot columns (commission VAT split, totals,
     * payout) from the provided base columns. Idempotent: rows that already
     * carry a host_payout figure — or have no gross at all — are untouched.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed> Only the completed snapshot columns.
     */
    public function completeSnapshot(array $attributes): array
    {
        $stayAmount = (int) ($attributes['stay_amount'] ?? 0);
        if ($stayAmount === 0 || (int) ($attributes['host_payout_amount'] ?? 0) > 0) {
            return [];
        }

        $vatEnabled = (bool) config('finance.vat.enabled', true);
        $vatRate = $vatEnabled ? (float) config('finance.vat.rate', 15.0) : 0.0;

        $guestVatAmount = (int) ($attributes['vat_amount'] ?? 0);
        $guestTotal = (int) ($attributes['total_amount'] ?? ($stayAmount + $guestVatAmount));

        // Commission side: VAT charged ON TOP of the commission, deducted from
        // the host's payout. Falls back to the booking's commission_rate when
        // the amount wasn't provided explicitly.
        $commissionAmount = (int) ($attributes['commission_amount']
            ?? round($stayAmount * (float) ($attributes['commission_rate'] ?? 0) / 100));
        $commissionVat = (int) round($commissionAmount * $vatRate / 100);
        $commissionTotal = $commissionAmount + $commissionVat;

        return [
            'vat_rate' => (float) ($attributes['vat_rate'] ?? $vatRate),
            'vat_amount' => $guestVatAmount,
            'total_amount' => $guestTotal,
            'commission_amount' => $commissionAmount,
            'commission_vat_rate' => $vatRate,
            'commission_vat_amount' => $commissionVat,
            'commission_total' => $commissionTotal,
            'host_payout_amount' => max(0, $stayAmount - $commissionTotal),
        ];
    }
}
