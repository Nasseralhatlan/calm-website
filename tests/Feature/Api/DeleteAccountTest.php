<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\OtpType;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;
use App\Services\Auth\OtpAuthService;
use App\Services\Otp\OtpService;
use App\Services\User\AccountDeletionService;

beforeEach(function (): void {
    $this->seed();
});

/** Issue an OTP for the user and return its (mock) code. */
function otpCodeFor(User $user): string
{
    app(OtpService::class)->issue($user, OtpType::Phone, (string) $user->phone);

    return '111111'; // mock SMS driver issues the fixed code
}

function delAcctPlace(User $host): Place
{
    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Listing', 'price' => 1000, 'check_in_time' => '15:00', 'check_out_time' => '12:00',
        'max_guests' => 4, 'status' => PlaceStatus::Active->value, 'review_status' => PlaceReviewStatus::Approved->value,
    ]);
}

function delAcctBooking(Place $place, User $guest, array $attrs = []): Booking
{
    return Booking::query()->create(array_merge([
        'place_id' => $place->id,
        'guest_user_id' => $guest->id,
        'host_user_id' => $place->host_user_id,
        'booking_status' => BookingStatus::Confirmed->value,
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'guests' => 2, 'nights' => 2, 'stay_amount' => 200000,
        'commission_rate' => 10, 'commission_amount' => 20000, 'vat_rate' => 15, 'vat_amount' => 30000,
        'total_amount' => 230000, 'payout_status' => 'not_paid',
    ], $attrs));
}

it('soft-deletes the account, frees the phone, and keeps the original', function (): void {
    $user = User::factory()->create(['phone' => '512000001', 'name' => 'Real Name']);
    $code = otpCodeFor($user);
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson('/api/user', ['code' => $code])
        ->assertOk();

    $row = User::withTrashed()->find($user->id);
    expect($row->trashed())->toBeTrue()
        ->and($row->phone)->toBeNull()
        ->and($row->deleted_phone)->toBe('512000001')
        ->and($row->name)->toBe('Real Name'); // data retained, not scrubbed

    // The old token no longer works.
    $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/user')->assertStatus(401);
});

it('hides the host listings on deletion and restores them on recovery', function (): void {
    $host = User::factory()->create(['phone' => '512000010']);
    $place = delAcctPlace($host);
    $code = otpCodeFor($host);
    $token = auth('api')->login($host);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson('/api/user', ['code' => $code])->assertOk();

    expect(Place::query()->find($place->id))->toBeNull()              // hidden from normal queries
        ->and(Place::withTrashed()->find($place->id)->trashed())->toBeTrue();

    // Restore brings the account + listing back, phone reclaimed.
    app(AccountDeletionService::class)->restore(User::withTrashed()->find($host->id));

    $host->refresh();
    expect($host->trashed())->toBeFalse()
        ->and($host->phone)->toBe('512000010')
        ->and($host->deleted_phone)->toBeNull()
        ->and(Place::query()->find($place->id))->not->toBeNull();
});

it('lets the same phone register a fresh account after deletion', function (): void {
    $user = User::factory()->create(['phone' => '512000020']);
    $code = otpCodeFor($user);
    $token = auth('api')->login($user);
    $this->withHeader('Authorization', 'Bearer '.$token)->deleteJson('/api/user', ['code' => $code])->assertOk();

    // A fresh OTP request for the same number creates a brand-new active user.
    app(OtpAuthService::class)->requestOtp(OtpType::Phone, '512000020');

    $fresh = User::query()->where('phone', '512000020')->first(); // excludes the trashed one
    expect($fresh)->not->toBeNull()
        ->and($fresh->id)->not->toBe($user->id);
});

it('rejects an invalid OTP', function (): void {
    $user = User::factory()->create(['phone' => '512000030']);
    otpCodeFor($user);
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson('/api/user', ['code' => '000000'])
        ->assertStatus(422);

    expect(User::query()->find($user->id))->not->toBeNull(); // untouched
});

it('blocks deletion when the guest has an active booking', function (): void {
    $host = User::factory()->create(['phone' => '512000040']);
    $guest = User::factory()->create(['phone' => '512000041']);
    delAcctBooking(delAcctPlace($host), $guest); // Confirmed
    $code = otpCodeFor($guest);
    $token = auth('api')->login($guest);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson('/api/user', ['code' => $code])
        ->assertStatus(422);

    expect(User::query()->find($guest->id))->not->toBeNull();
});

it('blocks deletion when the host has an active booking on a listing', function (): void {
    $host = User::factory()->create(['phone' => '512000050']);
    $guest = User::factory()->create(['phone' => '512000051']);
    delAcctBooking(delAcctPlace($host), $guest);
    $code = otpCodeFor($host);
    $token = auth('api')->login($host);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson('/api/user', ['code' => $code])
        ->assertStatus(422);
});

it('refuses to delete an admin account', function (): void {
    $admin = User::factory()->create(['phone' => '512000060', 'role' => UserRole::Admin->value]);
    $code = otpCodeFor($admin);
    $token = auth('api')->login($admin);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson('/api/user', ['code' => $code])
        ->assertStatus(422);
});
