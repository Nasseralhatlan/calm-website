<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Jobs\FinalizeBookingFinances;
use App\Models\Booking;
use App\Models\CityArea;
use App\Models\FinancialDocument;
use App\Models\HostTaxProfile;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;
use App\Services\Finance\BookingFinanceFinalizer;
use App\Services\Finance\QoyodSyncService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed();
    Carbon::setTestNow('2026-07-03 12:00:00');
    $this->host = User::factory()->create(['phone' => '516600001', 'name' => 'Qoyod Host']);
    $this->guest = User::factory()->create(['phone' => '517600001', 'name' => 'Qoyod Guest']);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function qoyodOn(): void
{
    config()->set('finance.qoyod.enabled', true);
    config()->set('finance.qoyod.api_key', 'test-key');
    config()->set('finance.qoyod.base_url', 'https://api.qoyod.test/2.0');
    config()->set('finance.qoyod.product_stay_id', 11);
    config()->set('finance.qoyod.product_service_fee_id', 12);
    config()->set('finance.qoyod.product_commission_id', 13);
    config()->set('finance.qoyod.moyasar_account_id', 7);
    config()->set('finance.qoyod.settlement_account_id', 8);
}

function qsyncBooking(User $host, User $guest): Booking
{
    $place = Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Qoyod place '.fake()->unique()->numerify('###'),
        'price' => 1000, 'check_in_time' => '15:00', 'check_out_time' => '12:00',
        'max_guests' => 4, 'status' => PlaceStatus::Active->value, 'review_status' => PlaceReviewStatus::Approved->value,
    ]);

    return Booking::query()->create([
        'place_id' => $place->id,
        'guest_user_id' => $guest->id,
        'host_user_id' => $host->id,
        'booking_status' => BookingStatus::Confirmed->value,
        'start_date' => '2026-07-01', 'end_date' => '2026-07-02',
        'check_in_time' => '15:00', 'check_out_time' => '12:00', 'checkout_next_day' => false,
        'guests' => 2, 'nights' => 2, 'stay_amount' => 200000,
        'commission_rate' => 10, 'commission_amount' => 20000, 'vat_rate' => 15, 'vat_amount' => 30000,
        'total_amount' => 230000, 'payout_status' => 'not_paid',
        'payment_status' => 'paid', 'payment_id' => 'pay_QOYOD1',
    ]);
}

it('stays fully local when Qoyod is disabled — zero HTTP', function (): void {
    Http::fake();
    $booking = qsyncBooking($this->host, $this->guest);

    (new FinalizeBookingFinances)->handle(app(BookingFinanceFinalizer::class), app(QoyodSyncService::class));

    Http::assertNothingSent();
    expect($booking->financialDocuments()->where('status', FinancialDocument::STATUS_ISSUED)->count())->toBe(3)
        ->and($booking->financialDocuments()->whereNotNull('external_document_id')->count())->toBe(0);
});

it('mirrors both invoices + payments into Qoyod with SAR decimals', function (): void {
    qoyodOn();
    Http::fake([
        'api.qoyod.test/2.0/customers' => Http::response(['contact' => ['id' => 77]], 201),
        'api.qoyod.test/2.0/invoices' => Http::sequence()
            ->push(['invoice' => ['id' => 501, 'reference' => 'G']], 201)
            ->push(['invoice' => ['id' => 502, 'reference' => 'C']], 201),
        'api.qoyod.test/2.0/invoice_payments' => Http::response(['id' => 9], 200),
    ]);

    $booking = qsyncBooking($this->host, $this->guest);
    (new FinalizeBookingFinances)->handle(app(BookingFinanceFinalizer::class), app(QoyodSyncService::class));

    $docs = $booking->financialDocuments()->get()->keyBy('document_subtype');

    $guestDoc = $docs[FinancialDocument::GUEST_BOOKING_INVOICE];
    expect($guestDoc->status)->toBe(FinancialDocument::STATUS_ISSUED)
        ->and($guestDoc->external_provider)->toBe('qoyod')
        ->and($guestDoc->external_document_id)->toBe('501');

    expect($docs[FinancialDocument::HOST_COMMISSION_INVOICE]->external_document_id)->toBe('502');

    // Contacts stored for reuse: guest on users, host on their tax profile.
    expect($this->guest->fresh()->qoyod_customer_id)->toBe('77')
        ->and(HostTaxProfile::query()->where('host_user_id', $this->host->id)->sole()->qoyod_customer_id)->toBe('77');

    // The guest invoice line carries SAR decimals + the snapshot VAT rate.
    Http::assertSent(function ($request) {
        if (! str_ends_with($request->url(), '/invoices') || ! str_ends_with((string) $request['invoice']['reference'], '-G')) {
            return false;
        }
        $line = $request['invoice']['line_items'][0];

        return $line['unit_price'] === '2000.00' && (float) $line['tax_percent'] === 15.0 && $line['product_id'] === 11;
    });
    // Commission invoice: 200.00 + 15% on top.
    Http::assertSent(function ($request) {
        if (! str_ends_with($request->url(), '/invoices') || ! str_ends_with((string) $request['invoice']['reference'], '-C')) {
            return false;
        }
        $line = $request['invoice']['line_items'][0];

        return $line['unit_price'] === '200.00' && $line['product_id'] === 13;
    });
    // Payments settle both invoices: guest 2,300.00 via Moyasar clearing,
    // commission 230.00 via the settlement/offset account.
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/invoice_payments')
        && $request['invoice_payment']['amount'] === '2300.00'
        && $request['invoice_payment']['account_id'] === '7');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/invoice_payments')
        && $request['invoice_payment']['amount'] === '230.00'
        && $request['invoice_payment']['account_id'] === '8');

    // Re-running the sweep sends nothing new (documents already issued).
    Http::assertSentCount(6); // 2 customers + 2 invoices + 2 payments
    (new FinalizeBookingFinances)->handle(app(BookingFinanceFinalizer::class), app(QoyodSyncService::class));
    Http::assertSentCount(6);
});

it('marks failed syncs and retries them on the next sweep', function (): void {
    qoyodOn();
    Http::fake([
        'api.qoyod.test/2.0/customers' => Http::response(['contact' => ['id' => 77]], 201),
        'api.qoyod.test/2.0/invoices' => Http::sequence()
            ->push('server error', 500)
            ->push('server error', 500)
            ->push(['invoice' => ['id' => 601, 'reference' => 'G']], 201)
            ->push(['invoice' => ['id' => 602, 'reference' => 'C']], 201),
        'api.qoyod.test/2.0/invoice_payments' => Http::response(['id' => 9], 200),
    ]);

    $booking = qsyncBooking($this->host, $this->guest);

    (new FinalizeBookingFinances)->handle(app(BookingFinanceFinalizer::class), app(QoyodSyncService::class));
    expect($booking->financialDocuments()->where('status', FinancialDocument::STATUS_FAILED)->count())->toBe(2);

    (new FinalizeBookingFinances)->handle(app(BookingFinanceFinalizer::class), app(QoyodSyncService::class));
    expect($booking->financialDocuments()->where('is_tax_document', true)->where('status', FinancialDocument::STATUS_ISSUED)->count())->toBe(2)
        ->and($booking->financialDocuments()->where('status', FinancialDocument::STATUS_FAILED)->count())->toBe(0);
});

it('resumes at the payment step after a payment failure — never duplicates the invoice', function (): void {
    qoyodOn();
    Http::fake([
        'api.qoyod.test/2.0/customers' => Http::response(['contact' => ['id' => 77]], 201),
        'api.qoyod.test/2.0/invoices' => Http::sequence()
            ->push(['invoice' => ['id' => 701, 'reference' => 'G']], 201)
            ->push(['invoice' => ['id' => 702, 'reference' => 'C']], 201),
        'api.qoyod.test/2.0/invoice_payments' => Http::sequence()
            ->push('boom', 500)        // guest payment fails on the first sweep
            ->push(['id' => 9], 200)   // commission payment succeeds
            ->push(['id' => 10], 200), // guest payment retried on the second sweep
    ]);

    $booking = qsyncBooking($this->host, $this->guest);
    (new FinalizeBookingFinances)->handle(app(BookingFinanceFinalizer::class), app(QoyodSyncService::class));

    $guestDoc = $booking->financialDocuments()
        ->where('document_subtype', FinancialDocument::GUEST_BOOKING_INVOICE)->sole();
    // The invoice DID get created before the payment died — id must be kept
    // so the retry resumes instead of colliding on the unique reference.
    expect($guestDoc->status)->toBe(FinancialDocument::STATUS_FAILED)
        ->and($guestDoc->external_document_id)->toBe('701');

    (new FinalizeBookingFinances)->handle(app(BookingFinanceFinalizer::class), app(QoyodSyncService::class));

    expect($guestDoc->fresh()->status)->toBe(FinancialDocument::STATUS_ISSUED);
    // 2 customers + exactly 2 invoice creations + 3 payment attempts.
    Http::assertSentCount(7);
});

it('returns a fresh expiring pdf link for a synced document', function (): void {
    qoyodOn();
    Http::fake([
        'api.qoyod.test/2.0/customers' => Http::response(['contact' => ['id' => 77]], 201),
        'api.qoyod.test/2.0/invoices' => Http::sequence()
            ->push(['invoice' => ['id' => 501]], 201)
            ->push(['invoice' => ['id' => 502]], 201),
        'api.qoyod.test/2.0/invoice_payments' => Http::response(['id' => 9], 200),
        'api.qoyod.test/2.0/invoices/501/pdf' => Http::response([
            'pdf_file' => 'https://cdn.qoyod.com/export/pdf/abc.pdf',
            'expire_at' => 'Fri, 03 Jul 2026 13:00:00 GMT',
        ]),
    ]);

    $booking = qsyncBooking($this->host, $this->guest);
    (new FinalizeBookingFinances)->handle(app(BookingFinanceFinalizer::class), app(QoyodSyncService::class));

    $guestDoc = $booking->financialDocuments()
        ->where('document_subtype', FinancialDocument::GUEST_BOOKING_INVOICE)->sole();

    expect(app(QoyodSyncService::class)->pdfUrl($guestDoc))->toBe('https://cdn.qoyod.com/export/pdf/abc.pdf');

    // Statements are internal — never a Qoyod link.
    $statement = $booking->financialDocuments()
        ->where('document_subtype', FinancialDocument::HOST_PAYOUT_STATEMENT)->sole();
    expect(app(QoyodSyncService::class)->pdfUrl($statement))->toBeNull();
});

it('creates documents locally issued when qoyod is enabled but the api key is missing', function (): void {
    qoyodOn();
    config()->set('finance.qoyod.api_key', ''); // flag on, key absent — misconfig
    Http::fake();

    $booking = qsyncBooking($this->host, $this->guest);
    (new FinalizeBookingFinances)->handle(app(BookingFinanceFinalizer::class), app(QoyodSyncService::class));

    Http::assertNothingSent();
    // Never stranded pending_provider: without a key the sync (rightly) won't
    // run, so documents must fall back to local issuance.
    expect($booking->financialDocuments()->where('status', FinancialDocument::STATUS_ISSUED)->count())->toBe(3)
        ->and($booking->financialDocuments()->where('status', FinancialDocument::STATUS_PENDING_PROVIDER)->count())->toBe(0);
});

it('fails the document instead of issuing when qoyod responds without an invoice id', function (): void {
    qoyodOn();
    Http::fake([
        'api.qoyod.test/2.0/customers' => Http::response(['contact' => ['id' => 77]], 201),
        'api.qoyod.test/2.0/invoices' => Http::response(['invoice' => ['reference' => 'G']], 201), // 2xx, no id
        'api.qoyod.test/2.0/invoice_payments' => Http::response(['id' => 9], 200),
    ]);

    $booking = qsyncBooking($this->host, $this->guest);
    (new FinalizeBookingFinances)->handle(app(BookingFinanceFinalizer::class), app(QoyodSyncService::class));

    $guestDoc = $booking->financialDocuments()
        ->where('document_subtype', FinancialDocument::GUEST_BOOKING_INVOICE)->sole();
    expect($guestDoc->status)->toBe(FinancialDocument::STATUS_FAILED)
        ->and($guestDoc->external_status)->toContain('no id');

    // Never a payment against an invoice we can't link, never silently issued.
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'invoice_payments'));
});

it('never duplicates a credit note when re-syncing a doc that already has a qoyod id', function (): void {
    qoyodOn();
    Http::fake([
        'api.qoyod.test/2.0/customers' => Http::response(['contact' => ['id' => 77]], 201),
        'api.qoyod.test/2.0/invoices' => Http::sequence()
            ->push(['invoice' => ['id' => 801, 'reference' => 'G']], 201)
            ->push(['invoice' => ['id' => 802, 'reference' => 'C']], 201),
        'api.qoyod.test/2.0/invoice_payments' => Http::response(['id' => 9], 200),
        'api.qoyod.test/2.0/credit_notes' => Http::sequence()
            ->push(['id' => 901, 'note_no' => 'CN-1'], 201)
            ->push(['id' => 902, 'note_no' => 'CN-2'], 201),
    ]);

    $booking = qsyncBooking($this->host, $this->guest);
    (new FinalizeBookingFinances)->handle(app(BookingFinanceFinalizer::class), app(QoyodSyncService::class));

    app(BookingFinanceFinalizer::class)->handleCancellation($booking->refresh());
    app(QoyodSyncService::class)->syncPendingDocuments();

    $guestNote = $booking->financialDocuments()
        ->where('document_subtype', FinancialDocument::GUEST_BOOKING_CREDIT_NOTE)->sole();
    expect($guestNote->status)->toBe(FinancialDocument::STATUS_ISSUED)
        ->and($guestNote->external_document_id)->toBe('901');

    // Simulate a crash AFTER Qoyod created the note but BEFORE the status
    // flip: the id survived, the doc re-enters the retry set.
    $guestNote->update(['status' => FinancialDocument::STATUS_FAILED]);
    app(QoyodSyncService::class)->syncPendingDocuments();

    expect($guestNote->fresh()->status)->toBe(FinancialDocument::STATUS_ISSUED)
        ->and($guestNote->fresh()->external_document_id)->toBe('901');

    // Exactly one credit_notes POST per note — none for the resume pass.
    $creditCalls = collect(Http::recorded())
        ->filter(fn ($pair): bool => str_contains($pair[0]->url(), 'credit_notes'))
        ->count();
    expect($creditCalls)->toBe(2);
});

it('persists the qoyod host contact even when no tax profile row exists yet', function (): void {
    qoyodOn();
    Http::fake([
        'api.qoyod.test/2.0/customers' => Http::sequence()
            ->push(['contact' => ['id' => 77]], 201)  // guest
            ->push(['contact' => ['id' => 78]], 201), // host — must be created ONCE
        'api.qoyod.test/2.0/invoices' => Http::sequence()
            ->push(['invoice' => ['id' => 811, 'reference' => 'G']], 201)
            ->push(['invoice' => ['id' => 812, 'reference' => 'C']], 201),
        'api.qoyod.test/2.0/invoice_payments' => Http::response(['id' => 9], 200),
    ]);

    $booking = qsyncBooking($this->host, $this->guest);
    // Documents exist but the finalizer's profile row does not — the sync
    // path must not depend on that ordering.
    app(BookingFinanceFinalizer::class)->finalize($booking);
    HostTaxProfile::query()->delete();

    app(QoyodSyncService::class)->syncPendingDocuments();

    // Contact id landed on a (re)created profile instead of being discarded —
    // no duplicate host customer on every future sync.
    $profile = HostTaxProfile::query()->where('host_user_id', $this->host->id)->sole();
    expect($profile->qoyod_customer_id)->toBe('78');

    $customerCalls = collect(Http::recorded())
        ->filter(fn ($pair): bool => str_ends_with($pair[0]->url(), '/customers'))
        ->count();
    expect($customerCalls)->toBe(2);
});

it('mirrors the settled host payout as a kind=paid receipt — سند صرف', function (): void {
    qoyodOn();
    Http::fake([
        'api.qoyod.test/2.0/customers' => Http::response(['contact' => ['id' => 77]], 201),
        'api.qoyod.test/2.0/invoices' => Http::sequence()
            ->push(['invoice' => ['id' => 821, 'reference' => 'G']], 201)
            ->push(['invoice' => ['id' => 822, 'reference' => 'C']], 201),
        'api.qoyod.test/2.0/invoice_payments' => Http::response(['id' => 9], 200),
        'api.qoyod.test/2.0/receipts' => Http::response(['receipt' => ['id' => 55, 'reference' => 'V-PAYOUT']], 201),
    ]);

    $booking = qsyncBooking($this->host, $this->guest);
    (new FinalizeBookingFinances)->handle(app(BookingFinanceFinalizer::class), app(QoyodSyncService::class));

    // The bank transfer settled → settlement records the payout + mints the voucher.
    $booking->refresh()->forceFill([
        'payout_status' => 'paid', 'payout_id' => 'po_q1',
        'payout_reference' => '1234567890123456', 'payout_paid_at' => now(),
    ])->save();
    app(BookingFinanceFinalizer::class)->recordPayoutPaid($booking->refresh(), 'moyasar');

    $voucher = $booking->financialDocuments()
        ->where('document_subtype', FinancialDocument::HOST_PAYOUT_VOUCHER)->sole();
    expect($voucher->status)->toBe(FinancialDocument::STATUS_PENDING_PROVIDER);

    app(QoyodSyncService::class)->syncPendingDocuments();

    expect($voucher->fresh()->status)->toBe(FinancialDocument::STATUS_ISSUED)
        ->and($voucher->fresh()->external_document_id)->toBe('55');

    // Money OUT of the Moyasar clearing account, to the host contact, with
    // the bank reference in the description — SAR decimals at the boundary.
    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/receipts')
        && $request['receipt']['kind'] === 'paid'
        && $request['receipt']['account_id'] === 7
        && $request['receipt']['contact_id'] === 77
        && $request['receipt']['amount'] === '1770.00'
        && str_contains((string) $request['receipt']['description'], '1234567890123456'));

    // Resume-safety: a crash after Qoyod created the receipt (id kept, status
    // back in the retry set) must NOT create a duplicate on the next sweep.
    $voucher->fresh()->update(['status' => FinancialDocument::STATUS_FAILED]);
    app(QoyodSyncService::class)->syncPendingDocuments();

    expect($voucher->fresh()->status)->toBe(FinancialDocument::STATUS_ISSUED);
    $receiptCalls = collect(Http::recorded())
        ->filter(fn ($pair): bool => str_ends_with($pair[0]->url(), '/receipts'))
        ->count();
    expect($receiptCalls)->toBe(1);
});
