<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\FinancialMovement;
use App\Models\HostTaxProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The finance entry points for the booking lifecycle (brief §11–§14):
 *
 *  - guest pays            → guest_payment movement (nothing else yet)
 *  - checkout + N hours    → finalize(): guest invoice, commission invoice,
 *                            payout statement, withheld/payable movements
 *  - Moyasar payout lands  → host_payout movement (via HostPayoutService)
 *  - admin cancels         → Case A nothing / Case B refund movement /
 *                            Case C credit notes + refund movement
 *
 * Everything is idempotent: documents via the (source, subtype) unique rule,
 * movements via an existence check per (source, movement_type).
 */
final class BookingFinanceFinalizer
{
    public function __construct(private readonly FinancialDocumentService $documents) {}

    /** Is this booking due for finance finalization (brief §11.3 conditions)? */
    public function isDue(Booking $booking, ?CarbonImmutable $now = null): bool
    {
        $now = $now ?? CarbonImmutable::now();
        $checkout = $booking->checkoutAt();

        return $booking->payment_status === 'paid'
            && in_array($booking->booking_status, [BookingStatus::Confirmed, BookingStatus::Completed], true)
            && $booking->financial_completed_at === null
            && $checkout !== null
            && $checkout->addHours((int) config('finance.invoice.issue_after_checkout_hours', 4))->lessThanOrEqualTo($now);
    }

    /**
     * Issue the full per-booking document set. Safe to call repeatedly and
     * from concurrent workers — the row lock + rechecks + idempotent document
     * creation make it single-shot.
     */
    public function finalize(Booking $booking): void
    {
        DB::transaction(function () use ($booking): void {
            /** @var Booking $locked */
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

            if ($locked === null || ! $this->isDue($locked)) {
                return;
            }

            $this->ensureHostTaxProfile($locked);

            $this->documents->guestBookingInvoice($locked);
            $this->documents->hostCommissionInvoice($locked);
            $this->documents->hostPayoutStatement($locked);

            // Money picture at settlement: Calm keeps the commission (+VAT)
            // out of the guest money it holds, and now owes the host the rest.
            $this->movement($locked, FinancialMovement::COMMISSION_WITHHELD, [
                'from_party_type' => 'host',
                'from_party_id' => $locked->host_user_id,
                'to_party_type' => 'calm',
                'amount' => (int) $locked->commission_total,
                'provider' => 'manual',
                'provider_reference' => $locked->reference,
                'status' => FinancialMovement::STATUS_SUCCEEDED,
                'occurred_at' => now(),
            ]);
            $this->movement($locked, FinancialMovement::HOST_PAYOUT_PAYABLE, [
                'from_party_type' => 'calm',
                'to_party_type' => 'host',
                'to_party_id' => $locked->host_user_id,
                'amount' => $locked->hostNetMinor(),
                'provider' => 'manual',
                'provider_reference' => $locked->reference,
                'status' => FinancialMovement::STATUS_PENDING,
            ]);

            // Per-document issue timestamps live on financial_documents; the
            // booking only carries the single "all documents done" gate.
            $locked->update(['financial_completed_at' => now()]);
        });
    }

    /** §11.2 — the guest's Moyasar payment landed (fired on Confirmed transition). */
    public function recordGuestPayment(Booking $booking): void
    {
        $this->movement($booking, FinancialMovement::GUEST_PAYMENT, [
            'from_party_type' => 'guest',
            'from_party_id' => $booking->guest_user_id,
            'to_party_type' => 'calm',
            'amount' => (int) $booking->total_amount,
            'provider' => 'moyasar',
            'provider_transaction_id' => $booking->payment_id,
            'status' => FinancialMovement::STATUS_SUCCEEDED,
            'occurred_at' => now(),
        ]);
    }

    /** §12 — the host's transfer settled: admin manual bank transfer ('bank') or automatic Moyasar payout ('moyasar'). */
    public function recordPayoutPaid(Booking $booking, string $provider = 'bank'): void
    {
        $this->movement($booking, FinancialMovement::HOST_PAYOUT, [
            'from_party_type' => 'calm',
            'to_party_type' => 'host',
            'to_party_id' => $booking->host_user_id,
            'amount' => $booking->hostNetMinor(),
            'provider' => $provider,
            'provider_reference' => $booking->payout_reference,
            'provider_transaction_id' => $booking->payout_id,
            'status' => FinancialMovement::STATUS_SUCCEEDED,
            'occurred_at' => now(),
        ]);

        // The payable is now settled.
        $booking->financialMovements()
            ->where('movement_type', FinancialMovement::HOST_PAYOUT_PAYABLE)
            ->where('status', FinancialMovement::STATUS_PENDING)
            ->update(['status' => FinancialMovement::STATUS_SUCCEEDED, 'occurred_at' => now()]);

        // سند صرف: the bank transfer out, mirrored to Qoyod by the sync sweep
        // so the Moyasar clearing account reconciles. Idempotent per booking.
        $this->documents->hostPayoutVoucher($booking);
    }

    /**
     * §14 — cancellation cases. A: never paid → nothing. B: paid but not yet
     * finalized → refund movement only. C: after invoice issuance → credit
     * notes + refund movement + withheld/payable reversals. The Moyasar refund
     * itself is executed by the operator; these are the records of it.
     */
    public function handleCancellation(Booking $booking): void
    {
        if ($booking->payment_status !== 'paid') {
            return; // Case A — zero financial footprint.
        }

        if ($booking->financial_completed_at === null) {
            // Case B.
            $this->movement($booking, FinancialMovement::GUEST_REFUND, [
                'from_party_type' => 'calm',
                'to_party_type' => 'guest',
                'to_party_id' => $booking->guest_user_id,
                'amount' => (int) $booking->total_amount,
                'provider' => 'moyasar',
                'provider_transaction_id' => $booking->payment_id,
                'status' => FinancialMovement::STATUS_SUCCEEDED,
                'occurred_at' => now(),
            ]);

            return;
        }

        // Case C — documents exist; never edit them, credit them. The refund
        // cash leaving the Moyasar account also gets its سند صرف so the
        // clearing account still reconciles after the reversal.
        $this->documents->guestBookingCreditNote($booking, (int) $booking->total_amount);
        $this->documents->hostCommissionCreditNote($booking);
        $this->documents->guestRefundVoucher($booking, (int) $booking->total_amount);

        $this->movement($booking, FinancialMovement::GUEST_REFUND, [
            'from_party_type' => 'calm',
            'to_party_type' => 'guest',
            'to_party_id' => $booking->guest_user_id,
            'amount' => (int) $booking->total_amount,
            'provider' => 'moyasar',
            'provider_transaction_id' => $booking->payment_id,
            'status' => FinancialMovement::STATUS_SUCCEEDED,
            'occurred_at' => now(),
        ]);

        $booking->financialMovements()
            ->whereIn('movement_type', [FinancialMovement::COMMISSION_WITHHELD, FinancialMovement::HOST_PAYOUT_PAYABLE])
            ->update(['status' => FinancialMovement::STATUS_REVERSED]);
    }

    /** Hosts must have a tax profile before Calm can invoice them commission. */
    private function ensureHostTaxProfile(Booking $booking): HostTaxProfile
    {
        return HostTaxProfile::query()->firstOrCreate(
            ['host_user_id' => $booking->host_user_id],
            [
                'host_type' => 'individual',
                'legal_name' => (string) ($booking->host?->name ?: 'Host '.$booking->host_user_id),
            ],
        );
    }

    /**
     * Record a movement once per (source, movement_type).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function movement(Booking $booking, string $type, array $attributes): void
    {
        $exists = $booking->financialMovements()
            ->where('movement_type', $type)
            ->exists();

        if ($exists) {
            return;
        }

        try {
            $booking->financialMovements()->create([
                'movement_type' => $type,
                'currency' => 'SAR',
                ...$attributes,
            ]);
        } catch (\Throwable $e) {
            // A movement record must never break the business transition that
            // triggered it (payment confirm, payout, cancel).
            Log::error('finance: movement record failed', [
                'booking' => $booking->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
