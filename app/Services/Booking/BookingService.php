<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Integrations\Payment\MoyasarGateway;
use App\Integrations\Payment\MoyasarInvoice;
use App\Models\Booking;
use App\Models\Place;
use App\Models\PlaceReview;
use App\Models\User;
use App\Services\Notification\NotificationService;
use App\Services\Place\PlaceAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class BookingService
{
    public function __construct(
        private readonly PlaceAvailabilityService $availability,
        private readonly MoyasarGateway $gateway,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Notify the guest when a booking reaches a terminal state — fired AFTER the
     * settling transaction commits, exactly once per real transition.
     */
    private function fireBookingNotification(Booking $booking, ?BookingStatus $transitionedTo): void
    {
        if ($transitionedTo === BookingStatus::Confirmed) {
            $this->notifications->bookingConfirmed($booking);   // guest
            $this->notifications->hostNewBooking($booking);     // host gets a booking
        }
        // No guest SMS on Expired: an expired hold is an abandoned/unpaid booking,
        // not a real cancellation. bookingCancelled() stays for a future cancel flow.
    }

    /**
     * "Click book": re-run the availability + pricing quote as the server-side
     * source of truth, hold the dates with a pending_payment booking, then open
     * a Moyasar hosted invoice and hand its checkout URL back to the client.
     *
     * The quote + insert run under a row lock on the place so two guests can't
     * race onto the same dates. The Moyasar call happens AFTER commit so we
     * never hold a DB lock across an external HTTP request.
     */
    public function create(User $user, Place $place, string $checkIn, string $checkOut, int $guests): Booking
    {
        // Single base instant for both the date-hold and the invoice expiry so
        // they can't drift apart.
        $holdExpiresAt = CarbonImmutable::now()->addMinutes((int) config('moyasar.hold_minutes', 10));

        $booking = DB::transaction(function () use ($user, $place, $checkIn, $checkOut, $guests, $holdExpiresAt): Booking {
            // Serialise concurrent bookings for this place.
            $locked = Place::query()->whereKey($place->id)->lockForUpdate()->first();
            if ($locked === null) {
                abort(404, 'Place not found.');
            }

            $quote = $this->availability->quote($locked, $checkIn, $checkOut, $guests);
            if ($quote === null) {
                abort(404, 'Place not found.');
            }
            if (! $quote['guests_ok']) {
                abort(422, 'This place allows at most '.$quote['max_guests'].' guests.');
            }
            if (! $quote['dates_available']) {
                abort(422, 'Those dates are no longer available.');
            }

            $pricing = $quote['pricing'];

            return Booking::query()->create([
                'place_id' => $locked->id,
                'guest_user_id' => $user->id,
                'host_user_id' => $locked->host_user_id,
                'booking_status' => BookingStatus::PendingPayment->value,
                'start_date' => $quote['check_in'],
                'end_date' => $quote['check_out'],
                'check_in_time' => $locked->check_in_time,
                'check_out_time' => $locked->check_out_time,
                'checkout_next_day' => $locked->checkout_next_day,
                'rules' => $locked->rules,
                'guests' => $guests,
                'booking_price' => (int) $locked->price * 100,  // base nightly snapshot, halalas
                'quantity' => $quote['days'],
                'booking_amount' => $pricing['subtotal_minor'],
                'commission_rate' => $pricing['commission_rate'],
                'commission_amount' => $pricing['commission_amount_minor'],
                'vat_rate' => $pricing['vat_rate'],
                'vat_amount' => $pricing['vat_amount_minor'],
                'total' => $pricing['total_minor'],
                'payout_status' => 'not_paid',
                'expires_at' => $holdExpiresAt,
            ]);
        });

        // Close the invoice a buffer BEFORE the date-hold, so Moyasar refuses
        // payment before the expiry sweep can release the dates (no "paid after
        // we expired" race). Floor to a minute out so a misconfigured buffer
        // never sends a past/at-now expiry.
        $invoiceExpiresAt = $holdExpiresAt->subMinutes((int) config('moyasar.invoice_buffer_minutes', 1));
        $earliest = CarbonImmutable::now()->addMinute();
        if ($invoiceExpiresAt->lessThan($earliest)) {
            $invoiceExpiresAt = $earliest;
        }

        try {
            $invoice = $this->gateway->createInvoice(
                amountMinor: $booking->total,
                description: 'Booking — '.$place->title,
                callbackUrl: route('payments.moyasar.webhook'),
                metadata: ['booking_id' => $booking->id],
                expiredAt: $invoiceExpiresAt,
            );
        } catch (RuntimeException $e) {
            // Free the dates immediately — a hold is worthless without a payment.
            $booking->update(['booking_status' => BookingStatus::Expired->value, 'payment_status' => 'creation_failed']);
            Log::error('Booking payment init failed', ['booking' => $booking->id, 'error' => $e->getMessage()]);
            abort(502, 'Could not start the payment. Please try again.');
        }

        $booking->update([
            'payment_id' => $invoice->id,
            'payment_status' => $invoice->status,
            'payment_url' => $invoice->url ?? null,
            'payment_response' => $invoice->raw,
        ]);

        return $booking->refresh();
    }

    /**
     * Bookings this user made as a guest (their "My bookings" list), newest first.
     *
     * @return Collection<int, Booking>
     */
    public function forGuest(User $user): Collection
    {
        return Booking::query()
            ->where('guest_user_id', $user->id)
            ->where('booking_status', '!=', BookingStatus::Expired->value)
            ->with(['place', 'place.coverPhoto', 'place.cityArea.city'])
            ->latest()
            ->get();
    }

    /**
     * Bookings guests placed on this user's places (the host's "Guest bookings"
     * list), newest first.
     *
     * @return Collection<int, Booking>
     */
    public function forHost(User $user): Collection
    {
        return Booking::query()
            ->where('host_user_id', $user->id)
            ->with(['place', 'place.coverPhoto', 'guest'])
            ->latest()
            ->get();
    }

    /**
     * Admin "all bookings" list. Optional free-text search matches the booking
     * reference (partial), the booking/place UUID (exact), or the guest's/host's
     * phone (partial, leading-zero tolerant). Optional $status narrows to a
     * status group ('cancelled' covers host/guest/admin cancellations).
     *
     * @return LengthAwarePaginator<int, Booking>
     */
    public function paginateForAdmin(?string $q = null, ?string $status = null, ?int $perPage = null): LengthAwarePaginator
    {
        $term = $q !== null && trim($q) !== '' ? trim($q) : null;

        return Booking::query()
            ->with(['place', 'place.coverPhoto', 'guest', 'host'])
            ->when($term, function ($query, string $term): void {
                $phone = ltrim($term, '0'); // phones are stored without the leading 0
                $query->where(function ($w) use ($term, $phone): void {
                    $w->where('reference', 'like', "%{$term}%")
                        ->orWhere('id', $term)
                        ->orWhere('place_id', $term)
                        ->orWhereHas('guest', fn ($g) => $g->where('phone', 'like', "%{$phone}%"))
                        ->orWhereHas('host', fn ($h) => $h->where('phone', 'like', "%{$phone}%"));
                });
            })
            ->when($status, fn ($query, string $status) => $query->whereIn('booking_status', self::statusGroup($status)))
            ->latest()
            ->paginate($perPage ?? config('pagination.per_page'))
            ->withQueryString();
    }

    /**
     * Booking counts per status filter for the admin chips. Returns keys:
     * all, pending_payment, confirmed, completed, cancelled, expired.
     *
     * @return array<string, int>
     */
    public function adminStatusCounts(): array
    {
        $byStatus = Booking::query()
            ->selectRaw('booking_status, COUNT(*) as aggregate')
            ->groupBy('booking_status')
            ->pluck('aggregate', 'booking_status');

        return [
            'all' => (int) $byStatus->sum(),
            'pending_payment' => (int) $byStatus->get(BookingStatus::PendingPayment->value, 0),
            'confirmed' => (int) $byStatus->get(BookingStatus::Confirmed->value, 0),
            'completed' => (int) $byStatus->get(BookingStatus::Completed->value, 0),
            'cancelled' => (int) collect(self::statusGroup('cancelled'))->sum(fn (string $s) => (int) $byStatus->get($s, 0)),
            'expired' => (int) $byStatus->get(BookingStatus::Expired->value, 0),
        ];
    }

    /**
     * Map a filter key to the booking_status values it covers.
     *
     * @return list<string>
     */
    private static function statusGroup(string $key): array
    {
        return match ($key) {
            'cancelled' => [
                BookingStatus::CanceledByHost->value,
                BookingStatus::CanceledByGuest->value,
                BookingStatus::CanceledByAdmin->value,
            ],
            default => [$key],
        };
    }

    /**
     * Admin cancels a CONFIRMED booking on behalf of the host or as the platform.
     * No refund is issued here (handled manually for now); the dates free up
     * automatically because cancelled bookings no longer count as active holds.
     * Both parties are notified after commit, with wording matching the canceller.
     */
    public function cancelByAdmin(Booking $booking, BookingStatus $as): Booking
    {
        abort_unless(
            in_array($as, [BookingStatus::CanceledByHost, BookingStatus::CanceledByAdmin], true),
            422,
        );
        abort_unless(
            $booking->booking_status === BookingStatus::Confirmed,
            422,
            __('Only confirmed bookings can be cancelled.'),
        );

        $booking = DB::transaction(function () use ($booking, $as): Booking {
            $booking->update([
                'booking_status' => $as->value,
                'canceled_at' => CarbonImmutable::now(),
            ]);

            return $booking->refresh();
        });

        if ($as === BookingStatus::CanceledByHost) {
            $this->notifications->bookingCanceledByHost($booking);
        } else {
            $this->notifications->bookingCanceledByAdmin($booking);
        }

        return $booking;
    }

    /**
     * The guest's own bookings for the mobile "My bookings" list — newest first,
     * paginated, each with the place summary (title/cover/city/type) the card
     * needs.
     *
     * @return LengthAwarePaginator<int, Booking>
     */
    public function forGuestPaginated(User $user, ?int $perPage = null): LengthAwarePaginator
    {
        $paginator = Booking::query()
            ->where('guest_user_id', $user->id)
            // Hide expired holds — a lapsed unpaid booking is noise to the guest.
            ->where('booking_status', '!=', BookingStatus::Expired->value)
            ->with(['place', 'place.coverPhoto', 'place.cityArea.city', 'place.type'])
            ->orderForGuestList()
            ->paginate($perPage ?? config('pagination.per_page'))
            ->withQueryString();

        // Attach the guest's place-scoped review to each booking (one query for
        // the whole page) so the card can show it + the "leave a review" CTA.
        $reviews = PlaceReview::query()
            ->where('guest_user_id', $user->id)
            ->whereIn('place_id', collect($paginator->items())->pluck('place_id')->unique()->all())
            ->get()
            ->keyBy('place_id');

        foreach ($paginator->items() as $booking) {
            $booking->setRelation('review', $reviews->get($booking->place_id));
        }

        return $paginator;
    }

    /**
     * The guest's still-payable holds: pending_payment bookings whose hold
     * hasn't lapsed yet. Soonest-to-expire first so the home screen can nudge
     * the user to finish paying before the dates are released. Each carries
     * `payment.url` so the client can reopen the Moyasar page.
     *
     * @return Collection<int, Booking>
     */
    public function pendingPaymentsForGuest(User $user): Collection
    {
        return Booking::query()
            ->where('guest_user_id', $user->id)
            ->where('booking_status', BookingStatus::PendingPayment->value)
            ->where('expires_at', '>', CarbonImmutable::now())
            ->with(['place', 'place.coverPhoto', 'place.cityArea.city', 'place.type'])
            ->orderBy('expires_at')
            ->get();
    }

    /**
     * Bookings guests placed on this host's places — the host app's "Bookings"
     * list. Newest first, paginated, with the place summary + the guest (so the
     * host can see/contact them).
     *
     * @return LengthAwarePaginator<int, Booking>
     */
    public function forHostPaginated(User $host, ?int $perPage = null): LengthAwarePaginator
    {
        return Booking::query()
            ->where('host_user_id', $host->id)
            ->with(['place', 'place.coverPhoto', 'place.cityArea.city', 'place.type', 'guest'])
            ->latest()
            ->paginate($perPage ?? config('pagination.per_page'))
            ->withQueryString();
    }

    /**
     * The host's earnings across all confirmed/completed bookings on their places.
     * A host earns the booking amount minus Calm's commission (VAT is the guest's
     * and is remitted, not earned). Split by payout settlement state.
     *
     * @return array{currency: string, bookings_count: int, total: float, total_minor: int, paid: float, paid_minor: int, not_paid: float, not_paid_minor: int}
     */
    public function earningsForHost(User $host): array
    {
        $rows = Booking::query()
            ->where('host_user_id', $host->id)
            ->whereIn('booking_status', [BookingStatus::Confirmed->value, BookingStatus::Completed->value])
            ->selectRaw('payout_status, SUM(booking_amount) as gross_minor, SUM(commission_amount) as commission_minor, COUNT(*) as cnt')
            ->groupBy('payout_status')
            ->get();

        $paidMinor = 0;
        $notPaidMinor = 0;
        $count = 0;

        foreach ($rows as $row) {
            $netMinor = (int) $row->gross_minor - (int) $row->commission_minor;
            $count += (int) $row->cnt;

            if ($row->payout_status === 'paid') {
                $paidMinor += $netMinor;
            } else {
                $notPaidMinor += $netMinor;
            }
        }

        $totalMinor = $paidMinor + $notPaidMinor;

        return [
            'currency' => 'SAR',
            'bookings_count' => $count,
            'total' => $totalMinor / 100,
            'total_minor' => $totalMinor,
            'paid' => $paidMinor / 100,
            'paid_minor' => $paidMinor,
            'not_paid' => $notPaidMinor / 100,
            'not_paid_minor' => $notPaidMinor,
        ];
    }

    /**
     * A single booking with everything the detail page needs — but only if the
     * viewer is a party to it (the guest who booked OR the host of the place).
     * Returns null otherwise so the controller can 404.
     */
    public function detailForViewer(Booking $booking, User $viewer): ?Booking
    {
        if ($booking->guest_user_id !== $viewer->id && $booking->host_user_id !== $viewer->id) {
            return null;
        }

        return $booking->load([
            'place', 'place.coverPhoto', 'place.type', 'place.cityArea.city',
            'place.publishedReviews.guest', 'guest', 'host',
        ]);
    }

    /**
     * Re-verify a booking's payment against Moyasar and settle it: confirm on
     * paid, expire otherwise. Safe to call repeatedly and from any trigger
     * (client poll, webhook, expiry sweep) — it no-ops once the booking has
     * left pending_payment.
     */
    public function checkPaymentStatus(Booking $booking): Booking
    {
        if ($booking->booking_status !== BookingStatus::PendingPayment) {
            return $booking;
        }

        if ($booking->payment_id === null) {
            return $booking->expires_at?->isPast() ? $this->forceExpire($booking) : $booking;
        }

        try {
            $invoice = $this->gateway->fetchInvoice($booking->payment_id);
        } catch (RuntimeException $e) {
            Log::warning('Booking payment status check failed', ['booking' => $booking->id, 'error' => $e->getMessage()]);

            return $booking->expires_at?->isPast() ? $this->forceExpire($booking) : $booking;
        }

        return $this->applyInvoice($booking, $invoice);
    }

    /**
     * Moyasar server-to-server webhook. The secret is verified, then we ignore
     * the body's claims and re-fetch the invoice from the API as the source of
     * truth before settling the booking.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload, ?string $providedSecret): void
    {
        $expected = config('moyasar.webhook_secret');
        if (! empty($expected)) {
            $secret = $providedSecret ?? (is_string($payload['secret_token'] ?? null) ? $payload['secret_token'] : null);
            if (! is_string($secret) || ! hash_equals((string) $expected, $secret)) {
                abort(401, 'Invalid webhook secret.');
            }
        }

        $bookingId = data_get($payload, 'data.metadata.booking_id') ?? data_get($payload, 'metadata.booking_id');
        $invoiceId = data_get($payload, 'data.id') ?? data_get($payload, 'data.invoice_id');

        $booking = match (true) {
            is_string($bookingId) => Booking::query()->find($bookingId),
            is_string($invoiceId) => Booking::query()->where('payment_id', $invoiceId)->first(),
            default => null,
        };

        if ($booking === null) {
            Log::warning('Moyasar webhook: no matching booking', ['booking_id' => $bookingId, 'invoice_id' => $invoiceId]);

            return; // 200 so Moyasar stops retrying an id we don't recognise
        }

        $this->checkPaymentStatus($booking);
    }

    /**
     * Release a still-unpaid hold when the guest backs out of the hosted payment
     * page. Acts ONLY on a pending_payment booking — a confirmed (or otherwise
     * settled) booking is returned untouched.
     *
     * It re-verifies against Moyasar first, so a guest who actually paid in a
     * race is confirmed rather than wrongly cancelled. If still unpaid, it cancels
     * the Moyasar invoice (best-effort) and frees the dates immediately — not
     * waiting for the 10-minute expiry.
     */
    public function cancelIfPending(Booking $booking): Booking
    {
        if ($booking->booking_status !== BookingStatus::PendingPayment) {
            return $booking;
        }

        // Source-of-truth re-check: confirms if paid, expires if already lapsed.
        $settled = $this->checkPaymentStatus($booking);
        if ($settled->booking_status !== BookingStatus::PendingPayment) {
            return $settled;
        }

        // Genuinely unpaid and still holding → close the invoice + release the dates now.
        if ($settled->payment_id !== null) {
            try {
                $this->gateway->cancelInvoice($settled->payment_id);
            } catch (RuntimeException $e) {
                Log::warning('Moyasar invoice cancel failed', ['booking' => $settled->id, 'error' => $e->getMessage()]);
            }
        }

        return $this->forceExpire($settled);
    }

    /**
     * Settle every pending hold that has passed its hold deadline. Each one is
     * re-checked against Moyasar first, so a payment that completed but never
     * notified us is still rescued (confirmed) rather than wrongly expired.
     *
     * @return int Number of holds processed.
     */
    public function expireStale(int $limit = 100): int
    {
        $stale = Booking::query()
            ->where('booking_status', BookingStatus::PendingPayment->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', CarbonImmutable::now())
            ->limit($limit)
            ->get();

        foreach ($stale as $booking) {
            try {
                $this->checkPaymentStatus($booking);
            } catch (\Throwable $e) {
                Log::error('Booking expiry sweep failed', ['booking' => $booking->id, 'error' => $e->getMessage()]);
            }
        }

        return $stale->count();
    }

    /**
     * Mark confirmed bookings whose checkout moment has passed as completed. A
     * booking stays "confirmed" through the stay and flips to "completed" once
     * the guest's checkout has elapsed — see Booking::checkoutAt() for the rule.
     * Scheduled hourly (see routes/console.php); a pure DB sweep with no
     * external calls. Stateless: all state lives in the DB.
     *
     * @return int Number of bookings completed.
     */
    public function completeEndedStays(int $limit = 200): int
    {
        // Cheap, indexed pre-filter on end_date; the exact checkout-time gate is
        // applied per row below (a booking ending today may still be mid-stay).
        $candidates = Booking::query()
            ->where('booking_status', BookingStatus::Confirmed->value)
            ->whereDate('end_date', '<=', CarbonImmutable::now()->toDateString())
            ->limit($limit)
            ->get();

        $now = CarbonImmutable::now();
        $completed = 0;

        foreach ($candidates as $booking) {
            $checkout = $booking->checkoutAt();
            if ($checkout === null || $checkout->greaterThan($now)) {
                continue; // checkout time hasn't arrived yet
            }

            if ($this->markCompleted($booking)) {
                $completed++;
            }
        }

        return $completed;
    }

    /**
     * Flip a single booking to completed under a row lock, re-reading status so
     * a concurrent sweep can't double-transition. Returns whether it flipped.
     */
    private function markCompleted(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking): bool {
            $fresh = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

            if ($fresh === null || $fresh->booking_status !== BookingStatus::Confirmed) {
                return false;
            }

            $fresh->booking_status = BookingStatus::Completed;
            $fresh->save();

            return true;
        });
    }

    /**
     * Apply a fetched invoice to the booking under a row lock, re-reading status
     * so a concurrent webhook/poll/sweep can't double-settle the same booking.
     */
    private function applyInvoice(Booking $booking, MoyasarInvoice $invoice): Booking
    {
        $transitionedTo = null;

        $result = DB::transaction(function () use ($booking, $invoice, &$transitionedTo): Booking {
            $fresh = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

            if ($fresh === null || $fresh->booking_status !== BookingStatus::PendingPayment) {
                return $fresh ?? $booking;
            }

            $fresh->payment_status = $invoice->status;
            $fresh->payment_response = $invoice->raw;
            $fresh->payment_status_check_attempts = $fresh->payment_status_check_attempts + 1;

            if ($invoice->isPaid()) {
                if ($invoice->amount !== $fresh->total) {
                    // Paid amount doesn't match what we quoted — never confirm.
                    Log::warning('Moyasar paid amount mismatch', [
                        'booking' => $fresh->id, 'expected' => $fresh->total, 'paid' => $invoice->amount,
                    ]);
                    $fresh->booking_status = BookingStatus::Expired;
                    $transitionedTo = BookingStatus::Expired;
                } else {
                    $fresh->booking_status = BookingStatus::Confirmed;
                    $fresh->payment_method = $invoice->paymentMethod();
                    $fresh->confirmed_at = CarbonImmutable::now();
                    $fresh->expires_at = null;
                    $transitionedTo = BookingStatus::Confirmed;
                }
            } elseif ($invoice->isCancelled() || $invoice->isFailed() || ($fresh->expires_at !== null && $fresh->expires_at->isPast())) {
                // Cancelled, failed, or the hold lapsed while still "initiated" —
                // let the dates go.
                $fresh->booking_status = BookingStatus::Expired;
                $transitionedTo = BookingStatus::Expired;
            }

            $fresh->save();

            return $fresh;
        });

        $this->fireBookingNotification($result, $transitionedTo);

        return $result;
    }

    /** Expire a stale pending hold (no usable invoice) under a row lock. */
    private function forceExpire(Booking $booking): Booking
    {
        $transitionedTo = null;

        $result = DB::transaction(function () use ($booking, &$transitionedTo): Booking {
            $fresh = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

            if ($fresh !== null && $fresh->booking_status === BookingStatus::PendingPayment) {
                $fresh->booking_status = BookingStatus::Expired;
                $fresh->save();
                $transitionedTo = BookingStatus::Expired;
            }

            return $fresh ?? $booking;
        });

        $this->fireBookingNotification($result, $transitionedTo);

        return $result;
    }
}
