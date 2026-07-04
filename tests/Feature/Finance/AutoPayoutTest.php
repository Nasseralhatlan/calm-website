<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Enums\UserRole;
use App\Integrations\Payment\MoyasarPayouts;
use App\Jobs\FinalizeBookingFinances;
use App\Jobs\ProcessDuePayouts;
use App\Jobs\ReconcileMoyasarPayouts;
use App\Models\Booking;
use App\Models\CityArea;
use App\Models\FinancialMovement;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;
use App\Services\Finance\BookingFinanceFinalizer;
use App\Services\Finance\HostPayoutService;
use App\Services\Finance\QoyodSyncService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed();
    // Checkout was 2026-07-02 12:00; the 24h hold clears exactly at 07-03 12:00.
    Carbon::setTestNow('2026-07-03 12:00:00');
    $this->host = User::factory()->create([
        'phone' => '516300001', 'name' => 'Payout Host',
        'bank' => 'Al Rajhi', 'bank_account' => 'SA4420000001234567891234',
    ]);
    $this->guest = User::factory()->create(['phone' => '517300001']);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function autoPayoutsOn(): void
{
    config()->set('moyasar.payouts_mode', 'auto');
    config()->set('moyasar.payout_account_id', 'src_TEST');
}

function apoBooking(User $host, User $guest, array $attrs = []): Booking
{
    $place = Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Auto payout place '.fake()->unique()->numerify('###'),
        'price' => 1000, 'check_in_time' => '15:00', 'check_out_time' => '12:00',
        'max_guests' => 4, 'status' => PlaceStatus::Active->value, 'review_status' => PlaceReviewStatus::Approved->value,
    ]);

    return Booking::query()->create(array_merge([
        'place_id' => $place->id,
        'guest_user_id' => $guest->id,
        'host_user_id' => $host->id,
        'booking_status' => BookingStatus::Completed->value,
        'start_date' => '2026-07-01', 'end_date' => '2026-07-02',
        'check_in_time' => '15:00', 'check_out_time' => '12:00', 'checkout_next_day' => false,
        'guests' => 2, 'booking_price' => 100000, 'quantity' => 2, 'host_gross_amount' => 200000,
        'commission_rate' => 10, 'commission_amount_ex_vat' => 20000, 'guest_vat_rate' => 15, 'guest_vat_amount' => 30000,
        'guest_total' => 230000, 'payout_status' => 'not_paid',
        'payment_status' => 'paid', 'payment_id' => 'pay_APO1',
        'financial_completed_at' => '2026-07-02 16:00:00',
    ], $attrs));
}

it('does nothing in manual mode — zero HTTP', function (): void {
    Http::fake();
    apoBooking($this->host, $this->guest);

    (new ProcessDuePayouts)->handle(app(HostPayoutService::class));

    Http::assertNothingSent();
});

it('transfers the host net with IBAN + deterministic sequence, then settles on reconcile', function (): void {
    autoPayoutsOn();
    $booking = apoBooking($this->host, $this->guest);
    // Real trail: the finalizer issued documents + the payable movement.
    $booking->forceFill(['financial_completed_at' => null])->save();
    (new FinalizeBookingFinances)->handle(app(BookingFinanceFinalizer::class), app(QoyodSyncService::class));

    $sequence = app(MoyasarPayouts::class)->sequenceNumberFor($booking->id);
    Http::fake([
        'api.moyasar.com/v1/payouts' => Http::response(['id' => 'po_1', 'status' => 'queued', 'sequence_number' => $sequence], 201),
        'api.moyasar.com/v1/payouts/*' => Http::response(['id' => 'po_1', 'status' => 'paid', 'sequence_number' => $sequence]),
    ]);

    (new ProcessDuePayouts)->handle(app(HostPayoutService::class));

    // 177,000 halalas (200,000 − 20,000 − 3,000 VAT) to the host's IBAN,
    // deduplicated by the booking-derived 16-digit sequence.
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/payouts')
        && $request['amount'] === 177000
        && $request['source_id'] === 'src_TEST'
        && $request['sequence_number'] === $sequence
        && $request['destination']['type'] === 'bank'
        && $request['destination']['iban'] === 'SA4420000001234567891234'
        && $request['destination']['name'] === 'Payout Host'
        && $request['destination']['mobile'] === '+966516300001'
        && $request['destination']['country'] === 'SA');

    $booking->refresh();
    expect($booking->payout_status)->toBe('processing')
        ->and($booking->payout_id)->toBe('po_1')
        ->and($booking->payout_failure)->toBeNull();

    (new ReconcileMoyasarPayouts)->handle(app(HostPayoutService::class));

    $booking->refresh();
    expect($booking->payout_status)->toBe('paid')
        ->and($booking->paid_out_at)->not->toBeNull()
        ->and($booking->payout_reference)->toBe($sequence);

    // Finance trail: host_payout via moyasar, payable settled.
    $payout = $booking->financialMovements()->where('movement_type', FinancialMovement::HOST_PAYOUT)->sole();
    expect($payout->amount)->toBe(177000)
        ->and($payout->provider)->toBe('moyasar')
        ->and($payout->provider_transaction_id)->toBe('po_1')
        ->and($payout->status)->toBe('succeeded');
    expect($booking->financialMovements()->where('movement_type', FinancialMovement::HOST_PAYOUT_PAYABLE)->sole()->status)
        ->toBe('succeeded');
});

it('requeues a bank-failed transfer with the reason and a fresh sequence for the retry', function (): void {
    autoPayoutsOn();
    $booking = apoBooking($this->host, $this->guest);

    Http::fake([
        'api.moyasar.com/v1/payouts' => Http::sequence()
            ->push(['id' => 'po_1', 'status' => 'queued'], 201)
            ->push(['id' => 'po_2', 'status' => 'queued'], 201),
        'api.moyasar.com/v1/payouts/*' => Http::response(['id' => 'po_1', 'status' => 'failed', 'failure_reason' => 'Invalid IBAN']),
    ]);

    (new ProcessDuePayouts)->handle(app(HostPayoutService::class));
    (new ReconcileMoyasarPayouts)->handle(app(HostPayoutService::class));

    $booking->refresh();
    expect($booking->payout_status)->toBe('not_paid')
        ->and($booking->payout_failure)->toContain('failed')
        ->and($booking->payout_failure)->toContain('Invalid IBAN')
        ->and($booking->payout_attempts)->toBe(1);

    // The sweep must NOT hammer a failed row — an admin retries explicitly.
    (new ProcessDuePayouts)->handle(app(HostPayoutService::class));
    expect($booking->refresh()->payout_status)->toBe('not_paid');

    app(HostPayoutService::class)->retry($booking->refresh());
    expect($booking->refresh()->payout_status)->toBe('processing')
        ->and($booking->payout_id)->toBe('po_2');

    // The consumed sequence advanced: retry carries a different number.
    $sequences = collect(Http::recorded())
        ->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/payouts'))
        ->map(fn ($pair) => $pair[0]['sequence_number'])
        ->values();
    expect($sequences)->toHaveCount(2)
        ->and($sequences[0])->not->toBe($sequences[1])
        ->and($sequences[0])->toBe(app(MoyasarPayouts::class)->sequenceNumberFor($booking->id, 0))
        ->and($sequences[1])->toBe(app(MoyasarPayouts::class)->sequenceNumberFor($booking->id, 1));
});

it('records a failure without calling Moyasar when the host has no IBAN', function (): void {
    autoPayoutsOn();
    Http::fake();
    $noBankHost = User::factory()->create(['phone' => '516300002', 'bank_account' => null]);
    $booking = apoBooking($noBankHost, $this->guest);

    (new ProcessDuePayouts)->handle(app(HostPayoutService::class));

    Http::assertNothingSent();
    expect($booking->refresh()->payout_status)->toBe('not_paid')
        ->and($booking->payout_failure)->toBe('Host has no IBAN on file.');
});

it('respects the hold window and the documents-before-money rule', function (): void {
    autoPayoutsOn();
    Http::fake();

    // Checkout 07-02 12:00 + 24h hold → payable 07-03 12:00; it is 11:00 now.
    Carbon::setTestNow('2026-07-03 11:00:00');
    $inHold = apoBooking($this->host, $this->guest);
    $noDocs = apoBooking($this->host, $this->guest, ['financial_completed_at' => null]);

    (new ProcessDuePayouts)->handle(app(HostPayoutService::class));
    Http::assertNothingSent();
    expect($inHold->refresh()->payout_status)->toBe('not_paid')
        ->and($inHold->isPayable())->toBeFalse();

    // Hold passed → the sweep pays it (documents-less row still waits).
    Carbon::setTestNow('2026-07-03 12:00:00');
    Http::fake(['api.moyasar.com/v1/payouts' => Http::response(['id' => 'po_9', 'status' => 'queued'], 201)]);
    (new ProcessDuePayouts)->handle(app(HostPayoutService::class));

    expect($inHold->refresh()->payout_status)->toBe('processing')
        ->and($noDocs->refresh()->payout_status)->toBe('not_paid');
});

it('shows the payout state and failures with a retry action on the admin booking page', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '598300001']);

    $processing = apoBooking($this->host, $this->guest, ['payout_status' => 'processing', 'payout_id' => 'po_55']);
    $failed = apoBooking($this->host, $this->guest, ['payout_failure' => 'Moyasar payout failed: Invalid IBAN']);

    $this->actingAs($admin, 'api')
        ->get("/admin/bookings/{$processing->id}")
        ->assertOk()
        ->assertSee('po_55');

    $this->actingAs($admin, 'api')
        ->get("/admin/bookings/{$failed->id}")
        ->assertOk()
        ->assertSee('Invalid IBAN');

    // Failed transfers surface on the bookings list behind the alert filter.
    $this->actingAs($admin, 'api')
        ->get('/admin/bookings?payout_failed=1')
        ->assertOk()
        ->assertSee($failed->reference)
        ->assertDontSee($processing->reference);

    // Retry re-fires the automatic transfer.
    autoPayoutsOn();
    Http::fake(['api.moyasar.com/v1/payouts' => Http::response(['id' => 'po_56', 'status' => 'queued'], 201)]);
    $this->actingAs($admin, 'api')
        ->post("/admin/bookings/{$failed->id}/payout/retry")
        ->assertRedirect();

    expect($failed->refresh()->payout_status)->toBe('processing')
        ->and($failed->payout_id)->toBe('po_56')
        ->and($failed->payout_failure)->toBeNull();
});
