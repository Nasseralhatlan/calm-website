<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Enums\UserRole;
use App\Jobs\FinalizeBookingFinances;
use App\Models\Booking;
use App\Models\CityArea;
use App\Models\FinancialDocument;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;
use App\Services\Finance\BookingFinanceFinalizer;
use App\Services\Finance\FinancialDocumentService;
use App\Services\Finance\QoyodSyncService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed();
    Carbon::setTestNow('2026-07-03 12:00:00');
    $this->host = User::factory()->create(['phone' => '516700001']);
    $this->guest = User::factory()->create(['phone' => '517700001']);

    $place = Place::query()->create([
        'host_user_id' => $this->host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Docs place', 'price' => 1000, 'check_in_time' => '15:00', 'check_out_time' => '12:00',
        'max_guests' => 4, 'status' => PlaceStatus::Active->value, 'review_status' => PlaceReviewStatus::Approved->value,
    ]);
    $this->booking = Booking::query()->create([
        'place_id' => $place->id,
        'guest_user_id' => $this->guest->id,
        'host_user_id' => $this->host->id,
        'booking_status' => BookingStatus::Confirmed->value,
        'start_date' => '2026-07-01', 'end_date' => '2026-07-02',
        'check_in_time' => '15:00', 'check_out_time' => '12:00', 'checkout_next_day' => false,
        'guests' => 2, 'nights' => 2, 'stay_amount' => 200000,
        'commission_rate' => 10, 'commission_amount' => 20000, 'vat_rate' => 15, 'vat_amount' => 30000,
        'total_amount' => 230000, 'payout_status' => 'not_paid',
        'payment_status' => 'paid', 'payment_id' => 'pay_DOCS1',
    ]);

    // Issue the documents locally (Qoyod disabled).
    (new FinalizeBookingFinances)->handle(app(BookingFinanceFinalizer::class), app(QoyodSyncService::class));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('lists only the viewer\'s own documents', function (): void {
    // Guest: exactly their booking invoice.
    $this->actingAs($this->guest, 'api')
        ->getJson('/api/finance-documents')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.document_subtype', 'guest_booking_invoice')
        ->assertJsonPath('data.items.0.total_amount', 230000)
        ->assertJsonPath('data.items.0.booking_reference', $this->booking->reference)
        ->assertJsonPath('data.items.0.has_pdf', false); // not synced to Qoyod

    // Host: commission invoice + payout statement.
    $subtypes = collect($this->actingAs($this->host, 'api')
        ->getJson('/api/finance-documents')
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->json('data.items'))->pluck('document_subtype')->sort()->values()->all();

    expect($subtypes)->toBe(['host_commission_invoice', 'host_payout_statement']);
});

it('404s another user\'s document and allows the admin', function (): void {
    $guestInvoice = $this->booking->financialDocuments()
        ->where('document_subtype', FinancialDocument::GUEST_BOOKING_INVOICE)->sole();

    // The host is not the buyer of the guest invoice → 404, never 403.
    $this->actingAs($this->host, 'api')
        ->getJson("/api/finance-documents/{$guestInvoice->id}/pdf-url")
        ->assertNotFound();

    // Admin passes the guard (and then hits the no-pdf-yet 409).
    $admin = User::factory()->create(['phone' => '598300001', 'role' => UserRole::Admin->value]);
    $this->actingAs($admin, 'api')
        ->getJson("/api/finance-documents/{$guestInvoice->id}/pdf-url")
        ->assertStatus(409);
});

it('mints a fresh expiring pdf link for the owner once synced to Qoyod', function (): void {
    $guestInvoice = $this->booking->financialDocuments()
        ->where('document_subtype', FinancialDocument::GUEST_BOOKING_INVOICE)->sole();
    $guestInvoice->update(['external_provider' => 'qoyod', 'external_document_id' => '501']);

    config()->set('finance.qoyod.enabled', true);
    config()->set('finance.qoyod.api_key', 'k');
    config()->set('finance.qoyod.base_url', 'https://api.qoyod.test/2.0');
    Http::fake(['api.qoyod.test/2.0/invoices/501/pdf' => Http::response([
        'pdf_file' => 'https://cdn.qoyod.com/export/pdf/doc.pdf',
    ])]);

    $this->actingAs($this->guest, 'api')
        ->getJson("/api/finance-documents/{$guestInvoice->id}/pdf-url")
        ->assertOk()
        ->assertJsonPath('data.url', 'https://cdn.qoyod.com/export/pdf/doc.pdf');
});

it('409s when the pdf is not available yet', function (): void {
    $guestInvoice = $this->booking->financialDocuments()
        ->where('document_subtype', FinancialDocument::GUEST_BOOKING_INVOICE)->sole();

    $this->actingAs($this->guest, 'api')
        ->getJson("/api/finance-documents/{$guestInvoice->id}/pdf-url")
        ->assertStatus(409);
});

it('requires authentication', function (): void {
    $this->getJson('/api/finance-documents')->assertStatus(401);
});

it('scopes the list to one booking with ?booking_id', function (): void {
    // A second booking for the same guest, also finalized.
    $second = Booking::query()->create([
        'place_id' => $this->booking->place_id,
        'guest_user_id' => $this->guest->id,
        'host_user_id' => $this->host->id,
        'booking_status' => BookingStatus::Completed->value,
        'start_date' => '2026-06-20', 'end_date' => '2026-06-21',
        'check_in_time' => '15:00', 'check_out_time' => '12:00', 'checkout_next_day' => false,
        'guests' => 2, 'nights' => 2, 'stay_amount' => 100000,
        'commission_rate' => 10, 'commission_amount' => 10000, 'vat_rate' => 15, 'vat_amount' => 15000,
        'total_amount' => 115000, 'payout_status' => 'not_paid',
        'payment_status' => 'paid', 'payment_id' => 'pay_DOCS2',
    ]);
    (new FinalizeBookingFinances)->handle(app(BookingFinanceFinalizer::class), app(QoyodSyncService::class));

    // Unfiltered: both bookings' guest invoices. Filtered: only the second's.
    $this->actingAs($this->guest, 'api')
        ->getJson('/api/finance-documents')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 2);

    $this->actingAs($this->guest, 'api')
        ->getJson("/api/finance-documents?booking_id={$second->id}")
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.items.0.total_amount', 115000)
        ->assertJsonPath('data.items.0.booking_reference', $second->reference);
});

it('returns an empty list for someone else\'s booking id — never leaks', function (): void {
    // The HOST's commission docs exist on this booking, but the GUEST filter
    // still only sees their own buyer-scoped documents; a stranger sees none.
    $stranger = User::factory()->create(['phone' => '517700099']);

    $this->actingAs($stranger, 'api')
        ->getJson("/api/finance-documents?booking_id={$this->booking->id}")
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 0);
});

it('rejects a malformed booking_id', function (): void {
    $this->actingAs($this->guest, 'api')
        ->getJson('/api/finance-documents?booking_id=not-a-uuid')
        ->assertStatus(422);
});

it('never lists voucher documents — internal cash mirrors stay internal', function (): void {
    // Both vouchers exist and are buyer-scoped to guest/host respectively…
    app(FinancialDocumentService::class)->hostPayoutVoucher($this->booking);
    app(FinancialDocumentService::class)->guestRefundVoucher($this->booking, 230000);

    // …but the mobile lists show only user-meaningful paper.
    $guestSubtypes = collect($this->actingAs($this->guest, 'api')
        ->getJson('/api/finance-documents')->assertOk()
        ->json('data.items'))->pluck('document_subtype');
    expect($guestSubtypes->all())->toBe([FinancialDocument::GUEST_BOOKING_INVOICE]);

    $hostSubtypes = collect($this->actingAs($this->host, 'api')
        ->getJson('/api/finance-documents')->assertOk()
        ->json('data.items'))->pluck('document_subtype')->sort()->values();
    expect($hostSubtypes->all())->toBe([
        FinancialDocument::HOST_COMMISSION_INVOICE,
        FinancialDocument::HOST_PAYOUT_STATEMENT,
    ]);
});
