<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Jobs\FinalizeBookingFinances;
use App\Models\Booking;
use App\Models\CityArea;
use App\Models\FinancialDocument;
use App\Models\FinancialMovement;
use App\Models\HostTaxProfile;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Finance\BookingFinanceFinalizer;
use App\Services\Finance\QoyodSyncService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->seed();
    Carbon::setTestNow('2026-07-03 12:00:00');
    $this->host = User::factory()->create(['phone' => '516500001', 'name' => 'Host Legal']);
    $this->guest = User::factory()->create(['phone' => '517500001']);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function fdocPlace(User $host): Place
{
    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Fin place '.fake()->unique()->numerify('###'),
        'price' => 1000, 'check_in_time' => '15:00', 'check_out_time' => '12:00',
        'max_guests' => 4, 'status' => PlaceStatus::Active->value, 'review_status' => PlaceReviewStatus::Approved->value,
    ]);
}

function fdocBooking(Place $place, User $guest, array $attrs = []): Booking
{
    return Booking::query()->create(array_merge([
        'place_id' => $place->id,
        'guest_user_id' => $guest->id,
        'host_user_id' => $place->host_user_id,
        'booking_status' => BookingStatus::Confirmed->value,
        // Checkout = 2026-07-02 12:00 → +4h issue delay passed at test-now.
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-02',
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'checkout_next_day' => false,
        'guests' => 2, 'nights' => 2, 'stay_amount' => 200000,
        'commission_rate' => 10, 'commission_amount' => 20000, 'vat_rate' => 15, 'vat_amount' => 30000,
        'total_amount' => 230000, 'payout_status' => 'not_paid',
        'payment_status' => 'paid', 'payment_id' => 'pay_TEST123', 'confirmed_at' => '2026-06-28 10:00:00',
    ], $attrs));
}

it('freezes the full money snapshot at creation (commission VAT on top)', function (): void {
    $booking = fdocBooking(fdocPlace($this->host), $this->guest);

    expect($booking->stay_amount)->toBe(200000)
        ->and($booking->vat_amount)->toBe(30000)
        ->and($booking->total_amount)->toBe(230000)
        ->and($booking->commission_amount)->toBe(20000)
        ->and($booking->commission_vat_amount)->toBe(3000)   // 15% on top of commission
        ->and($booking->commission_total)->toBe(23000)
        ->and($booking->host_payout_amount)->toBe(177000)
        ->and($booking->hostNetMinor())->toBe(177000);
});

it('issues the three documents + movements once checkout passed the issue delay', function (): void {
    $booking = fdocBooking(fdocPlace($this->host), $this->guest);

    (new FinalizeBookingFinances)->handle(app(BookingFinanceFinalizer::class), app(QoyodSyncService::class));
    // Second sweep must be a complete no-op.
    (new FinalizeBookingFinances)->handle(app(BookingFinanceFinalizer::class), app(QoyodSyncService::class));

    $booking->refresh();
    expect($booking->financial_completed_at)->not->toBeNull();

    $docs = $booking->financialDocuments()->get()->keyBy('document_subtype');
    expect($docs)->toHaveCount(3);

    $guestInvoice = $docs[FinancialDocument::GUEST_BOOKING_INVOICE];
    expect($guestInvoice->is_tax_document)->toBeTrue()
        ->and($guestInvoice->status)->toBe(FinancialDocument::STATUS_ISSUED) // Qoyod disabled → issued locally
        ->and($guestInvoice->buyer_id)->toBe($this->guest->id)
        ->and($guestInvoice->subtotal_amount)->toBe(200000)
        ->and($guestInvoice->vat_amount)->toBe(30000)
        ->and($guestInvoice->total_amount)->toBe(230000)
        ->and($guestInvoice->lines)->toHaveCount(1);

    $commissionInvoice = $docs[FinancialDocument::HOST_COMMISSION_INVOICE];
    expect($commissionInvoice->buyer_id)->toBe($this->host->id)
        ->and($commissionInvoice->subtotal_amount)->toBe(20000)
        ->and($commissionInvoice->vat_amount)->toBe(3000)
        ->and($commissionInvoice->total_amount)->toBe(23000);

    $statement = $docs[FinancialDocument::HOST_PAYOUT_STATEMENT];
    expect($statement->is_tax_document)->toBeFalse()
        ->and($statement->document_type)->toBe(FinancialDocument::TYPE_SETTLEMENT_STATEMENT)
        ->and($statement->total_amount)->toBe(177000)
        ->and($statement->lines)->toHaveCount(3);

    $movements = $booking->financialMovements()->get()->keyBy('movement_type');
    expect($movements[FinancialMovement::COMMISSION_WITHHELD]->amount)->toBe(23000)
        ->and($movements[FinancialMovement::COMMISSION_WITHHELD]->status)->toBe('succeeded')
        ->and($movements[FinancialMovement::HOST_PAYOUT_PAYABLE]->amount)->toBe(177000)
        ->and($movements[FinancialMovement::HOST_PAYOUT_PAYABLE]->status)->toBe('pending');

    // The host now has an auto-created tax profile (individual, legal name).
    $profile = HostTaxProfile::query()->where('host_user_id', $this->host->id)->sole();
    expect($profile->legal_name)->toBe('Host Legal')->and($profile->host_type)->toBe('individual');
});

it('does not issue anything before checkout + issue delay', function (): void {
    // Checkout today 12:00 → +4h is 16:00, test-now is 12:00 → not due.
    $booking = fdocBooking(fdocPlace($this->host), $this->guest, [
        'start_date' => '2026-07-02', 'end_date' => '2026-07-03',
    ]);

    (new FinalizeBookingFinances)->handle(app(BookingFinanceFinalizer::class), app(QoyodSyncService::class));

    expect($booking->refresh()->financial_completed_at)->toBeNull()
        ->and($booking->financialDocuments()->count())->toBe(0);
});

it('never issues documents for unpaid or expired bookings', function (): void {
    $place = fdocPlace($this->host);
    fdocBooking($place, $this->guest, ['payment_status' => null, 'payment_id' => null]);
    fdocBooking($place, $this->guest, ['booking_status' => BookingStatus::Expired->value]);

    (new FinalizeBookingFinances)->handle(app(BookingFinanceFinalizer::class), app(QoyodSyncService::class));

    expect(FinancialDocument::query()->count())->toBe(0);
});

it('records the guest payment movement idempotently', function (): void {
    $booking = fdocBooking(fdocPlace($this->host), $this->guest);
    $finance = app(BookingFinanceFinalizer::class);

    $finance->recordGuestPayment($booking);
    $finance->recordGuestPayment($booking);

    $movement = $booking->financialMovements()->where('movement_type', FinancialMovement::GUEST_PAYMENT)->sole();
    expect($movement->amount)->toBe(230000)
        ->and($movement->provider)->toBe('moyasar')
        ->and($movement->provider_transaction_id)->toBe('pay_TEST123');
});

it('cancellation before invoicing (Case B) refunds via Moyasar and records the movement', function (): void {
    Http::fake([
        'api.moyasar.com/v1/invoices/*' => Http::response([
            'id' => 'pay_TEST123', 'status' => 'paid', 'amount' => 230000,
            'payments' => [['id' => 'pmt_1', 'status' => 'paid', 'amount' => 230000, 'refunded' => 0]],
        ]),
        'api.moyasar.com/v1/payments/*/refund' => Http::response([
            'id' => 'pmt_1', 'status' => 'refunded', 'amount' => 230000, 'refunded' => 230000,
        ]),
    ]);

    $booking = fdocBooking(fdocPlace($this->host), $this->guest, [
        'start_date' => '2026-07-10', 'end_date' => '2026-07-11', // 7 days out — inside the refund window
    ]);

    app(BookingService::class)
        ->cancelByAdmin($booking, BookingStatus::CanceledByAdmin);

    // The real money went back first…
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/payments/pmt_1/refund'));

    expect($booking->refresh()->booking_status)->toBe(BookingStatus::CanceledByAdmin)
        ->and($booking->financialDocuments()->count())->toBe(0);

    $refund = $booking->financialMovements()->where('movement_type', FinancialMovement::GUEST_REFUND)->sole();
    expect($refund->amount)->toBe(230000);
});

it('refuses to cancel a paid booking inside the refund window — zero HTTP', function (): void {
    Http::fake();
    // Check-in 2026-07-05 15:00; window closes 4 days before → already closed at test-now (07-03).
    $booking = fdocBooking(fdocPlace($this->host), $this->guest, [
        'start_date' => '2026-07-05', 'end_date' => '2026-07-06',
    ]);

    expect(fn () => app(BookingService::class)->cancelByAdmin($booking, BookingStatus::CanceledByAdmin))
        ->toThrow(HttpException::class);

    Http::assertNothingSent();
    expect($booking->refresh()->booking_status)->toBe(BookingStatus::Confirmed)
        ->and($booking->financialMovements()->count())->toBe(0);
});

it('leaves the booking confirmed when the Moyasar refund fails', function (): void {
    Http::fake([
        'api.moyasar.com/v1/invoices/*' => Http::response([
            'payments' => [['id' => 'pmt_9', 'status' => 'paid', 'amount' => 230000, 'refunded' => 0]],
        ]),
        'api.moyasar.com/v1/payments/*/refund' => Http::response('nope', 500),
    ]);

    $booking = fdocBooking(fdocPlace($this->host), $this->guest, [
        'start_date' => '2026-07-10', 'end_date' => '2026-07-11',
    ]);

    expect(fn () => app(BookingService::class)->cancelByAdmin($booking, BookingStatus::CanceledByAdmin))
        ->toThrow(HttpException::class);

    expect($booking->refresh()->booking_status)->toBe(BookingStatus::Confirmed)
        ->and($booking->financialMovements()->count())->toBe(0);
});

it('skips the refund call when the payment was already refunded (crash-safe retry)', function (): void {
    Http::fake([
        'api.moyasar.com/v1/invoices/*' => Http::response([
            'payments' => [['id' => 'pmt_2', 'status' => 'refunded', 'amount' => 230000, 'refunded' => 230000]],
        ]),
    ]);

    $booking = fdocBooking(fdocPlace($this->host), $this->guest, [
        'start_date' => '2026-07-10', 'end_date' => '2026-07-11',
    ]);

    app(BookingService::class)->cancelByAdmin($booking, BookingStatus::CanceledByAdmin);

    // Only the invoice fetch — never a second refund.
    Http::assertSentCount(1);
    expect($booking->refresh()->booking_status)->toBe(BookingStatus::CanceledByAdmin);
});

it('cancellation after invoicing (Case C) credits both invoices', function (): void {
    // The 4-day refund window means the admin flow can never cancel after
    // checkout — Case C is kept as a defensive path (future dispute tooling),
    // so it's exercised directly on the finalizer here.
    $booking = fdocBooking(fdocPlace($this->host), $this->guest);
    (new FinalizeBookingFinances)->handle(app(BookingFinanceFinalizer::class), app(QoyodSyncService::class));

    app(BookingFinanceFinalizer::class)->handleCancellation($booking->refresh());

    $docs = $booking->financialDocuments()->get()->keyBy('document_subtype');
    expect($docs)->toHaveCount(5); // 3 originals + 2 credit notes

    expect($docs[FinancialDocument::GUEST_BOOKING_CREDIT_NOTE]->total_amount)->toBe(230000)
        ->and($docs[FinancialDocument::HOST_COMMISSION_CREDIT_NOTE]->total_amount)->toBe(23000)
        // Originals are never edited — only flipped to credited.
        ->and($docs[FinancialDocument::GUEST_BOOKING_INVOICE]->status)->toBe(FinancialDocument::STATUS_CREDITED)
        ->and($docs[FinancialDocument::HOST_COMMISSION_INVOICE]->status)->toBe(FinancialDocument::STATUS_CREDITED);

    expect($booking->financialMovements()->where('movement_type', FinancialMovement::GUEST_REFUND)->count())->toBe(1)
        ->and($booking->financialMovements()->where('movement_type', FinancialMovement::COMMISSION_WITHHELD)->sole()->status)->toBe('reversed')
        ->and($booking->financialMovements()->where('movement_type', FinancialMovement::HOST_PAYOUT_PAYABLE)->sole()->status)->toBe('reversed');
});
