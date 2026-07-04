<?php

declare(strict_types=1);

namespace App\Services\Finance;

/**
 * Calculates and freezes the full booking money snapshot (brief §2):
 *
 *   host_gross_amount      what belongs to the host before commission
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
        $hostGross = (int) ($attributes['host_gross_amount'] ?? 0);
        if ($hostGross === 0 || (int) ($attributes['host_payout_amount'] ?? 0) > 0) {
            return [];
        }

        $vatEnabled = (bool) config('finance.vat.enabled', true);
        $vatRate = $vatEnabled ? (float) config('finance.vat.rate', 15.0) : 0.0;

        $serviceFee = (int) ($attributes['guest_service_fee_amount'] ?? 0);
        $serviceFeeVat = (int) ($attributes['guest_service_fee_vat_amount'] ?? 0);

        $guestVatAmount = (int) ($attributes['guest_vat_amount'] ?? 0);
        $guestTotal = (int) ($attributes['guest_total'] ?? ($hostGross + $serviceFee + $guestVatAmount + $serviceFeeVat));

        // Commission side: VAT charged ON TOP of the commission, deducted from
        // the host's payout. Falls back to the booking's commission_rate when
        // the ex-VAT amount wasn't provided explicitly.
        $commissionExVat = (int) ($attributes['commission_amount_ex_vat']
            ?? round($hostGross * (float) ($attributes['commission_rate'] ?? 0) / 100));
        $commissionVat = (int) round($commissionExVat * $vatRate / 100);
        $commissionTotal = $commissionExVat + $commissionVat;

        return [
            'guest_vat_rate' => (float) ($attributes['guest_vat_rate'] ?? $vatRate),
            'guest_vat_amount' => $guestVatAmount,
            'guest_total' => $guestTotal,
            'guest_service_fee_amount' => $serviceFee,
            'guest_service_fee_vat_amount' => $serviceFeeVat,
            'commission_amount_ex_vat' => $commissionExVat,
            'commission_vat_rate' => $vatRate,
            'commission_vat_amount' => $commissionVat,
            'commission_total' => $commissionTotal,
            'host_payout_amount' => max(0, $hostGross - $commissionTotal),
        ];
    }
}
