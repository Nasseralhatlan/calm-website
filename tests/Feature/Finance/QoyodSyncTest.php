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
        'guests' => 2, 'booking_price' => 100000, 'quantity' => 2, 'booking_amount' => 200000,
        'commission_rate' => 10, 'commission_amount' => 20000, 'vat_rate' => 15, 'vat_amount' => 30000,
        'total' => 230000, 'payout_status' => 'not_paid',
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
