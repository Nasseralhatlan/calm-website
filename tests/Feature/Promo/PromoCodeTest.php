<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Enums\PromoDiscountType;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\CityArea;
use App\Models\FinancialDocument;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\Finance\FinancialDocumentService;
use Illuminate\Support\Facades\Http;

/*
 * Seeded settings: commission 10%, VAT 15%. The test place costs 1000/day and
 * every stay here is 2 inclusive days → stay 2000 SR (200000 halalas),
 * commission 20000, commission VAT 3000, host payout 177000.
 */
beforeEach(function (): void {
    $this->seed();

    Http::fake([
        'api.moyasar.com/v1/invoices' => Http::response([
            'id' => 'inv_promo', 'status' => 'initiated', 'amount' => 0,
            'url' => 'https://checkout.moyasar.com/invoices/inv_promo', 'metadata' => [],
        ]),
        '*' => Http::response([], 200),
    ]);

    $host = User::factory()->create(['phone' => '515'.fake()->unique()->numerify('######')]);
    $this->place = Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Promo test place',
        'description' => 'Desc.',
        'price' => 1000,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ]);
    $this->guest = User::factory()->create(['phone' => '516'.fake()->unique()->numerify('######')]);
    $this->stay = ['check_in' => now()->addDays(3)->toDateString(), 'check_out' => now()->addDays(4)->toDateString()];
});

function promo(array $attrs = []): PromoCode
{
    return PromoCode::query()->create(array_merge([
        'code' => 'CALM20',
        'discount_type' => PromoDiscountType::Percentage->value,
        'discount_value' => 20,
        'is_active' => true,
    ], $attrs));
}

function bookWithPromo($test, User $guest, string $code)
{
    return $test->actingAs($guest, 'api')->postJson("/api/places/{$test->place->id}/bookings", [
        ...$test->stay, 'guests' => 2, 'promo_code' => $code,
    ]);
}

it('re-prices the quote: discount off the stay, VAT on the discounted amount', function (): void {
    promo();

    $this->getJson("/api/places/{$this->place->id}/quote?".http_build_query([...$this->stay, 'promo_code' => 'calm20']))
        ->assertOk()
        ->assertJsonPath('data.pricing.subtotal', 2000)
        ->assertJsonPath('data.pricing.discount', 400)
        ->assertJsonPath('data.pricing.vat', 240)
        ->assertJsonPath('data.pricing.total', 1840)
        ->assertJsonPath('data.pricing.total_minor', 184000)
        ->assertJsonPath('data.promo.applied', true)
        ->assertJsonPath('data.promo.code', 'CALM20');
});

it('explains a refused code and keeps the full price', function (): void {
    promo(['ends_at' => now()->subDay()]);

    $this->getJson("/api/places/{$this->place->id}/quote?".http_build_query([...$this->stay, 'promo_code' => 'CALM20']))
        ->assertOk()
        ->assertJsonPath('data.promo.applied', false)
        ->assertJsonPath('data.promo.reason', 'expired')
        ->assertJsonPath('data.pricing.discount', 0)
        ->assertJsonPath('data.pricing.total', 2300);

    $this->getJson("/api/places/{$this->place->id}/quote?".http_build_query([...$this->stay, 'promo_code' => 'NOPE']))
        ->assertJsonPath('data.promo.reason', 'not_found');
});

it('is Calm-funded: the host payout and commission ignore the discount', function (): void {
    promo();

    bookWithPromo($this, $this->guest, 'CALM20')
        ->assertCreated()
        ->assertJsonPath('data.pricing.discount', 400)
        ->assertJsonPath('data.pricing.promo_code', 'CALM20')
        ->assertJsonPath('data.pricing.total_minor', 184000);

    $booking = Booking::query()->sole();
    expect($booking->discount_amount)->toBe(40000)
        ->and($booking->total_amount)->toBe(184000)
        ->and($booking->vat_amount)->toBe(24000)
        // Host side is exactly what it would be without the promo.
        ->and($booking->commission_amount)->toBe(20000)
        ->and($booking->commission_total)->toBe(23000)
        ->and($booking->host_payout_amount)->toBe(177000)
        // 20% off vs a 10% commission → Calm pays 200 SR out of pocket.
        ->and($booking->calmNetMinor())->toBe(-20000)
        // Books balance: guest money = host + Calm (commission − discount) + VAT.
        ->and($booking->total_amount)->toBe(
            $booking->host_payout_amount + $booking->commission_total - $booking->discount_amount + $booking->vat_amount,
        );
});

it('enforces the per-guest limit and releases expired holds', function (): void {
    promo(['max_uses_per_user' => 1]);

    bookWithPromo($this, $this->guest, 'CALM20')->assertCreated();

    // Second try while the first hold is live → refused on promo_code.
    $this->stay = ['check_in' => now()->addDays(10)->toDateString(), 'check_out' => now()->addDays(11)->toDateString()];
    bookWithPromo($this, $this->guest, 'CALM20')->assertStatus(422)->assertJsonValidationErrors('promo_code', 'data.errors');

    // The first hold lapses unpaid → its use is released.
    Booking::query()->update(['booking_status' => BookingStatus::Expired->value]);
    bookWithPromo($this, $this->guest, 'CALM20')->assertCreated();
});

it('applies a per-guest-limited code before sign-in, then enforces the limit at booking', function (): void {
    promo(['max_uses_per_user' => 1]);
    bookWithPromo($this, $this->guest, 'CALM20')->assertCreated();
    app('auth')->forgetGuards();

    $this->stay = ['check_in' => now()->addDays(10)->toDateString(), 'check_out' => now()->addDays(11)->toDateString()];
    $quoteUrl = "/api/places/{$this->place->id}/quote?".http_build_query([...$this->stay, 'promo_code' => 'CALM20']);

    // Signed out: the guest is unknown, so the code applies provisionally.
    $this->getJson($quoteUrl)->assertJsonPath('data.promo.applied', true);

    // Signed in as the guest who already used it: refused on the quote…
    $this->actingAs($this->guest, 'api')->getJson($quoteUrl)
        ->assertJsonPath('data.promo.applied', false)
        ->assertJsonPath('data.promo.reason', 'already_used');

    // …and on the booking that follows the login step.
    bookWithPromo($this, $this->guest, 'CALM20')->assertStatus(422)->assertJsonValidationErrors('promo_code', 'data.errors');
});

it('stops at the total usage limit', function (): void {
    promo(['max_uses' => 1]);
    bookWithPromo($this, $this->guest, 'CALM20')->assertCreated();

    $other = User::factory()->create(['phone' => '517'.fake()->unique()->numerify('######')]);
    $this->stay = ['check_in' => now()->addDays(10)->toDateString(), 'check_out' => now()->addDays(11)->toDateString()];
    bookWithPromo($this, $other, 'CALM20')->assertStatus(422);
});

it('respects the minimum stay and the max-discount cap', function (): void {
    promo(['code' => 'BIG', 'min_stay_amount' => 300000]);
    $this->getJson("/api/places/{$this->place->id}/quote?".http_build_query([...$this->stay, 'promo_code' => 'BIG']))
        ->assertJsonPath('data.promo.reason', 'min_stay')
        ->assertJsonPath('data.promo.min_stay', 3000);

    promo(['code' => 'CAPPED', 'max_discount_amount' => 5000]);
    $this->getJson("/api/places/{$this->place->id}/quote?".http_build_query([...$this->stay, 'promo_code' => 'CAPPED']))
        ->assertJsonPath('data.pricing.discount', 50);
});

it('flags codes that exceed Calm\'s commission', function (): void {
    expect(promo(['code' => 'A', 'discount_value' => 10])->commissionCheck(10.0)['exceeds'])->toBeFalse()
        ->and(promo(['code' => 'B', 'discount_value' => 12.5])->commissionCheck(10.0))
        ->toMatchArray(['exceeds' => true, 'over_by_rate' => 2.5]);

    // Fixed 100 SR at 10% breaks even on a 1000 SR stay.
    $fixed = promo(['code' => 'C', 'discount_type' => 'fixed', 'discount_value' => 100]);
    expect($fixed->commissionCheck(10.0))->toMatchArray(['exceeds' => true, 'break_even_stay_minor' => 100000]);
    $fixed->update(['min_stay_amount' => 100000]);
    expect($fixed->commissionCheck(10.0)['exceeds'])->toBeFalse();
});

it('issues the guest invoice on the discounted amount', function (): void {
    promo();
    bookWithPromo($this, $this->guest, 'CALM20')->assertCreated();

    $invoice = app(FinancialDocumentService::class)->guestBookingInvoice(Booking::query()->sole());
    expect($invoice->document_subtype)->toBe(FinancialDocument::GUEST_BOOKING_INVOICE)
        ->and($invoice->subtotal_amount)->toBe(160000)
        ->and($invoice->vat_amount)->toBe(24000)
        ->and($invoice->total_amount)->toBe(184000)
        ->and($invoice->lines->first()->description)->toContain('CALM20');
});

it('lets an admin create codes and warns when one exceeds the commission', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value]);
    $this->actingAs($admin, 'api');

    $this->post(route('admin.promo-codes.store'), [
        'code' => 'summer25', 'discount_type' => 'percentage', 'discount_value' => 25, 'is_active' => 1,
    ])->assertRedirect(route('admin.promo-codes.index'))->assertSessionHas('promo_warning', 'SUMMER25');

    $this->post(route('admin.promo-codes.store'), [
        'code' => 'small5', 'discount_type' => 'percentage', 'discount_value' => 5, 'is_active' => 1,
        'starts_at' => '2026-11-01', 'ends_at' => '2026-11-30',
    ])->assertSessionMissing('promo_warning');

    $small = PromoCode::query()->where('code', 'SMALL5')->sole();
    // Riyadh-day boundaries: 2026-11-01 00:00 +03 = 2026-10-31 21:00 UTC.
    expect($small->starts_at->toDateTimeString())->toBe('2026-10-31 21:00:00');

    $this->get(route('admin.promo-codes.index'))
        ->assertOk()
        ->assertSee('SUMMER25')
        ->assertSee('يتجاوز بـ 15%'); // admin defaults to Arabic

    // Duplicate codes (case-insensitive) are refused.
    $this->post(route('admin.promo-codes.store'), [
        'code' => 'Summer25', 'discount_type' => 'percentage', 'discount_value' => 5,
    ])->assertSessionHasErrors('code');
});

it('only deactivates a code that bookings already used', function (): void {
    $promo = promo();
    bookWithPromo($this, $this->guest, 'CALM20')->assertCreated();

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin->value]), 'api')
        ->delete(route('admin.promo-codes.destroy', $promo));

    expect($promo->refresh()->is_active)->toBeFalse()
        ->and(Booking::query()->sole()->promo_code)->toBe('CALM20');
});
