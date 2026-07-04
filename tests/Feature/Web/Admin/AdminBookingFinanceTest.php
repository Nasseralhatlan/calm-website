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
use App\Services\Finance\QoyodSyncService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed();
    Carbon::setTestNow('2026-07-03 12:00:00');
    $this->admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '598400001']);
    $this->host = User::factory()->create(['phone' => '516400001', 'bank' => 'Al Rajhi', 'bank_account' => 'SA4420000001234567891234']);
    $this->guest = User::factory()->create(['phone' => '517400001']);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function finPanelBooking(User $host, User $guest, array $attrs = []): Booking
{
    $place = Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Fin panel place '.fake()->unique()->numerify('###'),
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
        'guests' => 2, 'nights' => 2, 'stay_amount' => 200000,
        'commission_rate' => 10, 'commission_amount' => 20000, 'guest_vat_rate' => 15, 'guest_vat_amount' => 30000,
        'guest_total' => 230000, 'payout_status' => 'not_paid',
        'payment_status' => 'paid', 'payment_id' => 'pay_PANEL1',
    ], $attrs));
}

it('shows documents, money trail and payout state on the booking page after finalization', function (): void {
    $booking = finPanelBooking($this->host, $this->guest);
    (new FinalizeBookingFinances)->handle(app(BookingFinanceFinalizer::class), app(QoyodSyncService::class));

    $this->actingAs($this->admin, 'api')
        ->get("/admin/bookings/{$booking->id}")
        ->assertOk()
        // Panel headers (Arabic default locale).
        ->assertSee('المستندات المالية')
        ->assertSee('حركة الأموال')
        ->assertSee('تحويل المضيف')
        // The three documents with the payout total, and the two movements.
        ->assertSee('فاتورة الضيف')
        ->assertSee('بيان مستحقات المضيف')
        ->assertSee('1,770.00')
        ->assertSee('عمولة كالم (مخصومة)')
        ->assertSee('مستحق للمضيف')
        // Payable exactly at test-now (checkout +24h hold) → queued for the sweep.
        ->assertSee('في قائمة التحويل')
        // Host IBAN for reference.
        ->assertSee('SA4420000001234567891234');
});

it('shows the hold badge before the window passes and awaiting-invoices before finalization', function (): void {
    Carbon::setTestNow('2026-07-03 11:00:00'); // hold clears at 12:00

    $noDocs = finPanelBooking($this->host, $this->guest);
    $this->actingAs($this->admin, 'api')
        ->get("/admin/bookings/{$noDocs->id}")
        ->assertOk()
        ->assertSee('بانتظار إصدار الفواتير');

    $inHold = finPanelBooking($this->host, $this->guest, ['financial_completed_at' => '2026-07-02 16:00:00']);
    $this->actingAs($this->admin, 'api')
        ->get("/admin/bookings/{$inHold->id}")
        ->assertOk()
        ->assertSee('فترة الحجز حتى');
});

it('redirects to a fresh Qoyod pdf link, and flashes when none exists', function (): void {
    config()->set('finance.qoyod.enabled', true);
    config()->set('finance.qoyod.api_key', 'test-key');
    config()->set('finance.qoyod.base_url', 'https://api.qoyod.test/2.0');
    Http::fake([
        'api.qoyod.test/2.0/invoices/501/pdf' => Http::response([
            'pdf_file' => 'https://cdn.qoyod.com/export/pdf/fresh.pdf',
        ]),
    ]);

    $booking = finPanelBooking($this->host, $this->guest);
    $synced = $booking->financialDocuments()->create([
        'document_type' => FinancialDocument::TYPE_INVOICE,
        'document_subtype' => FinancialDocument::GUEST_BOOKING_INVOICE,
        'seller_type' => 'calm', 'buyer_type' => 'guest', 'buyer_id' => $this->guest->id,
        'direction' => 'sales', 'status' => FinancialDocument::STATUS_ISSUED, 'is_tax_document' => true,
        'subtotal_amount' => 200000, 'vat_amount' => 30000, 'total_amount' => 230000,
        'issued_at' => now(), 'external_provider' => 'qoyod', 'external_document_id' => '501',
    ]);
    $statement = $booking->financialDocuments()->create([
        'document_type' => FinancialDocument::TYPE_SETTLEMENT_STATEMENT,
        'document_subtype' => FinancialDocument::HOST_PAYOUT_STATEMENT,
        'seller_type' => 'calm', 'buyer_type' => 'host', 'buyer_id' => $this->host->id,
        'direction' => 'internal', 'status' => FinancialDocument::STATUS_ISSUED, 'is_tax_document' => false,
        'subtotal_amount' => 200000, 'vat_amount' => 0, 'total_amount' => 177000,
        'issued_at' => now(),
    ]);

    $this->actingAs($this->admin, 'api')
        ->get("/admin/finance-documents/{$synced->id}/pdf")
        ->assertRedirect('https://cdn.qoyod.com/export/pdf/fresh.pdf');

    $this->actingAs($this->admin, 'api')
        ->from("/admin/bookings/{$booking->id}")
        ->get("/admin/finance-documents/{$statement->id}/pdf")
        ->assertRedirect("/admin/bookings/{$booking->id}")
        ->assertSessionHas('error');
});

it('is admin-only', function (): void {
    $booking = finPanelBooking($this->host, $this->guest, ['payout_failure' => 'Moyasar payout failed.']);
    $doc = $booking->financialDocuments()->create([
        'document_type' => FinancialDocument::TYPE_INVOICE,
        'document_subtype' => FinancialDocument::GUEST_BOOKING_INVOICE,
        'seller_type' => 'calm', 'buyer_type' => 'guest', 'buyer_id' => $this->guest->id,
        'direction' => 'sales', 'status' => FinancialDocument::STATUS_ISSUED, 'is_tax_document' => true,
        'subtotal_amount' => 200000, 'vat_amount' => 30000, 'total_amount' => 230000,
        'issued_at' => now(), 'external_provider' => 'qoyod', 'external_document_id' => '501',
    ]);

    // EnsureAdmin bounces non-admin web users to their profile.
    $this->actingAs($this->host, 'api')
        ->get("/admin/finance-documents/{$doc->id}/pdf")
        ->assertRedirect('/profile');
    $this->actingAs($this->host, 'api')
        ->post("/admin/bookings/{$booking->id}/payout/retry")
        ->assertRedirect('/profile');

    expect($booking->fresh()->payout_status)->toBe('not_paid');
});
