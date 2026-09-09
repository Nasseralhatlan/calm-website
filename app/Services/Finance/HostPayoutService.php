<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\BookingStatus;
use App\Integrations\Payment\MoyasarPayouts;
use App\Models\Booking;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Executes host payouts automatically through Moyasar Payouts, honouring the
 * documents-before-money rule (Booking::isPayable): completed stay, financial
 * documents issued, payout hold window passed. MOYASAR_PAYOUTS_MODE stays
 * 'manual' until the Moyasar product + bank account are live, so nothing here
 * runs until the flag flips (manual mode = the sweep pauses and payouts queue).
 *
 * Manual settlement: while automatic payouts can't run (e.g. the company bank
 * isn't supported as a Moyasar payout source), an admin transfers from the
 * real bank and records it via markPaidManually() — same finance trail as an
 * automatic settle (movement provider 'bank', سند صرف, host SMS).
 *
 * Failure model: a create-time error (Moyasar rejection, API down) records
 * payout_failure and leaves the row not_paid — visible in the admin queue
 * with a Retry button; the sweep skips it until an admin retries, so a broken
 * transfer isn't hammered every 15 minutes. A bank-level failure discovered by
 * reconciliation (failed/returned/canceled) also advances payout_attempts,
 * because that sequence_number was consumed without moving money.
 *
 * A missing IBAN is NOT a failure — it's a wait state the HOST resolves: the
 * sweep skips the row (no payout_failure), nudges the host once a day to add
 * their bank details, and pays automatically on the first sweep after they do.
 */
final class HostPayoutService
{
    public function __construct(
        private readonly MoyasarPayouts $client,
        private readonly BookingFinanceFinalizer $finalizer,
        private readonly NotificationService $notifications,
    ) {}

    /** Auto mode = flag on + a registered payout (source) account. */
    public function autoModeEnabled(): bool
    {
        return config('moyasar.payouts_mode') === 'auto'
            && (string) config('moyasar.payout_account_id') !== '';
    }

    /**
     * Start a transfer for every payable booking. Scheduled every 15 minutes;
     * a no-op in manual mode. Returns how many transfers were started.
     */
    public function executeDuePayouts(): int
    {
        if (! $this->autoModeEnabled()) {
            return 0;
        }

        $started = 0;

        // SQL narrows to candidates; the exact hold-window check runs in
        // isPayable() because checkout time derives from end_date +
        // check_out_time + checkout_next_day.
        Booking::query()
            ->where('booking_status', BookingStatus::Completed->value)
            ->where('payout_status', 'not_paid')
            ->whereNotNull('financial_completed_at')
            ->whereNull('payout_failure')
            ->with('host')
            ->chunkById(50, function ($bookings) use (&$started): void {
                foreach ($bookings as $booking) {
                    if ($booking->isPayable() && $this->execute($booking)) {
                        $started++;
                    }
                }
            });

        return $started;
    }

    /**
     * Start one booking's transfer. True = accepted by Moyasar (row moves to
     * `processing`, settles via reconciliation); false = failed locally — the
     * reason lands in payout_failure and the row stays in the queue.
     */
    public function execute(Booking $booking): bool
    {
        $host = $booking->host;

        if ($host === null) {
            // A deleted host account can't add bank details — real failure.
            $booking->update(['payout_failure' => 'Host account missing.']);

            return false;
        }

        $iban = strtoupper(str_replace(' ', '', (string) ($host->bank_account ?? '')));

        if ($iban === '') {
            // Wait state, not a failure: leave payout_failure NULL so every
            // sweep re-checks for free (this branch costs no HTTP), nudge the
            // host to add their IBAN (deduped to once a day), and the payout
            // fires automatically on the first sweep after they do.
            $this->notifications->hostIbanNeeded($booking);

            return false;
        }

        // Moyasar refuses transfers under 100 halalas (SR 1) — discovered
        // live. Record a readable reason instead of burning an API call.
        if ($booking->hostNetMinor() < 100) {
            $booking->update(['payout_failure' => 'Payout below the Moyasar minimum of SR 1.00.']);

            return false;
        }

        try {
            $payout = $this->client->createPayout(
                $booking->hostNetMinor(),
                array_filter([
                    'type' => 'bank',
                    'iban' => $iban,
                    // Beneficiary = the name as written at the BANK when the
                    // host provided it; profile name otherwise. Banks screen
                    // the name at their own discretion (Moyasar documents no
                    // matching rule) — accuracy here avoids returned transfers.
                    'name' => (string) ($host->bank_account_name ?: $host->name ?? ''),
                    'mobile' => $this->mobile((string) ($host->phone ?? '')),
                    'country' => 'SA',
                    'city' => (string) config('moyasar.payout_default_city', 'Riyadh'),
                ], fn (string $value): bool => $value !== ''),
                $this->client->sequenceNumberFor($booking->id, (int) $booking->payout_attempts),
                "Calm host payout {$booking->reference}",
                // Every Moyasar object we create carries the full identity
                // set — searchable in the dashboard, echoed in webhooks.
                [
                    'booking_id' => (string) $booking->id,
                    'booking_reference' => (string) $booking->reference,
                    'guest_id' => (string) $booking->guest_user_id,
                    'host_id' => (string) $booking->host_user_id,
                    'attempt' => (string) $booking->payout_attempts,
                ],
            );
        } catch (Throwable $e) {
            // Ambiguous by design: the payout may or may not exist at Moyasar
            // (e.g. timeout). The attempt counter does NOT advance, so a retry
            // reuses the same sequence_number — Moyasar dedups it if the first
            // create actually landed, instead of paying twice.
            $booking->update(['payout_failure' => mb_substr($e->getMessage(), 0, 500)]);

            return false;
        }

        $booking->update([
            'payout_status' => 'processing',
            'payout_id' => isset($payout['id']) ? (string) $payout['id'] : null,
            'payout_failure' => null,
        ]);

        // Sandbox (and same-bank transfers) can settle synchronously.
        if (($payout['status'] ?? null) === 'paid') {
            $this->settle($booking->refresh(), $payout);
        }

        return true;
    }

    /**
     * Poll every in-flight transfer. paid → settle (audit trail + movements);
     * failed/returned/canceled → back to the queue with the bank's reason;
     * queued/initiated and transient API errors → check again next run.
     * Returns how many rows changed state.
     */
    public function reconcileProcessingPayouts(): int
    {
        $changed = 0;

        Booking::query()
            ->where('payout_status', 'processing')
            ->whereNotNull('payout_id')
            ->chunkById(50, function ($bookings) use (&$changed): void {
                foreach ($bookings as $booking) {
                    try {
                        $payout = $this->client->fetchPayout((string) $booking->payout_id);
                    } catch (Throwable $e) {
                        Log::warning('payouts: reconcile fetch failed', ['booking' => $booking->id, 'error' => $e->getMessage()]);

                        continue;
                    }

                    $status = (string) ($payout['status'] ?? '');

                    if ($status === 'paid') {
                        $this->settle($booking, $payout);
                        $changed++;
                    } elseif (in_array($status, ['failed', 'returned', 'canceled'], true)) {
                        // No money moved, but the sequence_number is spent —
                        // advance the attempt so a retry gets a fresh one.
                        $reason = isset($payout['failure_reason']) ? ': '.$payout['failure_reason'] : '.';
                        $booking->update([
                            'payout_status' => 'not_paid',
                            'payout_failure' => mb_substr("Moyasar payout {$status}{$reason}", 0, 500),
                            'payout_attempts' => (int) $booking->payout_attempts + 1,
                        ]);
                        $changed++;
                    }
                }
            });

        return $changed;
    }

    /**
     * Admin "Retry" on a failed row: clear the failure and re-execute this
     * one booking now (the sweep skips failed rows on purpose).
     */
    public function retry(Booking $booking): bool
    {
        // Same gate as the sweep: in manual mode execute() would fire at
        // Moyasar with an empty source account and just record a new failure.
        if (! $this->autoModeEnabled()) {
            throw ValidationException::withMessages([
                'payout' => __('Automatic payouts are disabled — configure Moyasar payouts (mode + account) before retrying.'),
            ]);
        }

        if (! $booking->isPayable()) {
            throw ValidationException::withMessages([
                'payout' => __('This booking is not payable right now — it must be completed, invoiced, unpaid and past its hold window.'),
            ]);
        }

        $booking->update(['payout_failure' => null]);

        return $this->execute($booking->refresh());
    }

    /**
     * Admin settles the payout by hand — a bank transfer from the company
     * account or cash handed over — and records it with a reference. Same
     * finance trail as an automatic settle (movement, سند صرف, host SMS), only
     * the provider differs ('bank' | 'cash').
     *
     * Works ahead of the normal gates: if the documents have not been issued
     * yet (early release, stay still running) they are force-issued first, so
     * the books never lag the money. What it will NOT do is pay out money the
     * guest never paid, or double-pay a transfer already in flight.
     *
     * @param  'bank'|'cash'  $method
     */
    public function markPaidManually(
        Booking $booking,
        string $reference,
        string $method = 'bank',
        ?string $note = null,
        ?User $admin = null,
    ): void {
        $this->assertSettleable($booking);

        // Documents first, always.
        $this->finalizer->finalizeNow($booking);
        $booking->refresh();

        // Did this jump the queue? Judged AFTER issuing the documents, so a
        // booking that was simply waiting on the invoice job isn't mislabelled
        // as an early release — only a genuinely-not-due one is.
        $forced = ! $booking->isPayable();

        $booking->update([
            'payout_status' => 'paid',
            'payout_paid_at' => now(),
            'payout_reference' => $reference,
            'payout_method' => $method,
            'payout_note' => $note,
            'payout_settled_by' => $admin?->id,
            'payout_forced_at' => $forced ? now() : null,
            'payout_failure' => null,
        ]);

        $this->finalizer->recordPayoutPaid($booking->refresh(), $method);

        $this->notifications->hostPayoutPaid($booking);
    }

    /**
     * Admin releases the money NOW through Moyasar, ahead of the stay
     * completing / the hold window closing. Issues the documents first, then
     * fires the same transfer the automatic sweep would have.
     *
     * True = accepted by Moyasar (row goes `processing`, settles via
     * reconciliation); false = failed locally, reason in payout_failure.
     */
    public function payoutNow(Booking $booking, ?string $note = null, ?User $admin = null): bool
    {
        if (! $this->autoModeEnabled()) {
            throw ValidationException::withMessages([
                'payout' => __('Automatic payouts are disabled — configure Moyasar payouts (mode + account), or settle this one by hand.'),
            ]);
        }

        $this->assertSettleable($booking);

        $this->finalizer->finalizeNow($booking);
        $booking->refresh();

        // See markPaidManually(): judged after the documents exist.
        $forced = ! $booking->isPayable();

        $booking->update([
            'payout_note' => $note,
            'payout_settled_by' => $admin?->id,
            'payout_forced_at' => $forced ? now() : null,
            // The admin is deliberately re-firing; clear any stale failure so
            // execute() is not skipped by the queue's own guard.
            'payout_failure' => null,
        ]);

        return $this->execute($booking->refresh());
    }

    /**
     * Shared guards for any admin-initiated settlement. These are the money-real
     * invariants — unlike the timing gates, none of them bend for an admin.
     */
    private function assertSettleable(Booking $booking): void
    {
        // Never while a Moyasar transfer is in flight — settling both would
        // pay the host twice. The reconciler owns `processing` rows.
        if ($booking->payout_status === 'processing') {
            throw ValidationException::withMessages([
                'payout' => __('A Moyasar transfer is already in progress for this booking — wait for it to settle or fail before settling by hand.'),
            ]);
        }

        if ($booking->payout_status !== 'not_paid') {
            throw ValidationException::withMessages([
                'payout' => __('This payout is already settled.'),
            ]);
        }

        // You cannot hand a host money the guest never paid.
        if ($booking->payment_status !== 'paid') {
            throw ValidationException::withMessages([
                'payout' => __('The guest has not paid for this booking — there is nothing to pay out.'),
            ]);
        }

        if (! in_array($booking->booking_status, [BookingStatus::Confirmed, BookingStatus::Completed], true)) {
            throw ValidationException::withMessages([
                'payout' => __('Only confirmed or completed bookings can be paid out.'),
            ]);
        }
    }

    /**
     * The transfer landed: stamp the audit fields and write the finance trail
     * (host_payout movement, payable → succeeded) with provider moyasar.
     *
     * @param  array<string, mixed>  $payout
     */
    private function settle(Booking $booking, array $payout): void
    {
        $booking->update([
            'payout_status' => 'paid',
            'payout_paid_at' => now(),
            'payout_reference' => (string) ($payout['sequence_number'] ?? $payout['id'] ?? $booking->payout_id),
            'payout_method' => 'moyasar',
            'payout_failure' => null,
        ]);

        $this->finalizer->recordPayoutPaid($booking->refresh(), 'moyasar');

        // The money actually moved — tell the host.
        $this->notifications->hostPayoutPaid($booking);
    }

    /** Local Saudi mobile (stored without the leading 0) → +966 E.164. */
    private function mobile(string $phone): string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return '';
        }

        return str_starts_with($phone, '+') ? $phone : '+966'.ltrim($phone, '0');
    }
}
