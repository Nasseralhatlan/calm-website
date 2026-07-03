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
 * seeders/tests/legacy paths) and is never recomputed afterwards — the
 * snapshot IS the agreement.
 */
final class BookingPricingService
{
    /**
     * Fill the finance-snapshot columns from the legacy pricing fields when
     * they weren't provided explicitly. Idempotent: rows that already carry a
     * host_gross_amount are returned untouched.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed> Only the completed snapshot columns.
     */
    public function completeSnapshot(array $attributes): array
    {
        $bookingAmount = (int) ($attributes['booking_amount'] ?? 0);
        if ((int) ($attributes['host_gross_amount'] ?? 0) > 0 || $bookingAmount === 0) {
            return [];
        }

        $vatEnabled = (bool) config('finance.vat.enabled', true);
        $vatRate = $vatEnabled ? (float) config('finance.vat.rate', 15.0) : 0.0;

        $serviceFee = (int) ($attributes['guest_service_fee_amount'] ?? 0);
        $serviceFeeVat = (int) ($attributes['guest_service_fee_vat_amount'] ?? 0);

        // Guest side mirrors the legacy columns (guest-facing prices are
        // unchanged by this module).
        $guestVatAmount = (int) ($attributes['vat_amount'] ?? 0);
        $guestTotal = (int) ($attributes['total'] ?? ($bookingAmount + $serviceFee + $guestVatAmount + $serviceFeeVat));

        // Commission side: VAT charged ON TOP of the commission, deducted from
        // the host's payout.
        $commissionExVat = (int) ($attributes['commission_amount'] ?? 0);
        $commissionVat = (int) round($commissionExVat * $vatRate / 100);
        $commissionTotal = $commissionExVat + $commissionVat;

        return [
            'host_gross_amount' => $bookingAmount,
            'guest_vat_rate' => (float) ($attributes['vat_rate'] ?? $vatRate),
            'guest_vat_amount' => $guestVatAmount,
            'guest_total' => $guestTotal,
            'guest_service_fee_amount' => $serviceFee,
            'guest_service_fee_vat_amount' => $serviceFeeVat,
            'commission_amount_ex_vat' => $commissionExVat,
            'commission_vat_rate' => $vatRate,
            'commission_vat_amount' => $commissionVat,
            'commission_total' => $commissionTotal,
            'host_payout_amount' => max(0, $bookingAmount - $commissionTotal),
        ];
    }
}
