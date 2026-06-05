<?php

declare(strict_types=1);

use App\Contracts\SmsDeliveryContract;
use App\Enums\OtpType;
use App\Enums\UserRole;
use App\Models\Otp;
use App\Models\User;
use App\Services\Otp\OtpService;
use Illuminate\Support\Facades\Hash;
use Tests\Support\TestSmsDelivery;

beforeEach(function (): void {
    $this->sink = new TestSmsDelivery;
    $this->app->instance(SmsDeliveryContract::class, $this->sink);
});

// ─── request-otp ─────────────────────────────────────────────────────────────

it('issues an otp for a new phone number and auto-creates the user', function (): void {
    $response = $this->postJson('/api/auth/otp/request', [
        'type' => 'phone',
        'identifier' => '512345678',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['status', 'message', 'data' => ['type', 'identifier']])
        ->assertJsonPath('status', 200)
        ->assertJsonPath('data.type', 'phone')
        ->assertJsonPath('data.identifier', '512345678');

    expect(User::where('phone', '512345678')->exists())->toBeTrue();
    expect(Otp::count())->toBe(1);
    expect($this->sink->sent)->toHaveCount(1);
    expect($this->sink->sent[0]['phone'])->toBe('512345678');
    expect($this->sink->lastCode())->toMatch('/^\d{6}$/');
});

it('rejects invalid phone format', function (): void {
    $this->postJson('/api/auth/otp/request', [
        'type' => 'phone',
        'identifier' => '0512345678',
    ])->assertStatus(422)
        ->assertJsonPath('status', 422)
        ->assertJsonStructure(['data' => ['errors' => ['identifier']]]);

    $this->postJson('/api/auth/otp/request', [
        'type' => 'phone',
        'identifier' => '4123456789',
    ])->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['identifier']]]);
});

it('rejects invalid email format', function (): void {
    $this->postJson('/api/auth/otp/request', [
        'type' => 'email',
        'identifier' => 'not-an-email',
    ])->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['identifier']]]);
});

it('rejects unknown otp type', function (): void {
    $this->postJson('/api/auth/otp/request', [
        'type' => 'sms',
        'identifier' => '512345678',
    ])->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['type']]]);
});

it('reuses the active otp instead of creating a new one or sending another sms', function (): void {
    $user = User::factory()->create(['phone' => '512345678']);

    $first = app(OtpService::class)->issue($user, OtpType::Phone, '512345678');
    $second = app(OtpService::class)->issue($user, OtpType::Phone, '512345678');

    expect($first->id)->toBe($second->id);
    expect(Otp::count())->toBe(1);
    expect($this->sink->sent)->toHaveCount(1);
});

it('issues a fresh otp once the previous one is expired', function (): void {
    $user = User::factory()->create(['phone' => '512345678']);

    $first = app(OtpService::class)->issue($user, OtpType::Phone, '512345678');
    $first->forceFill(['expires_at' => now()->subSecond()])->save();

    $second = app(OtpService::class)->issue($user, OtpType::Phone, '512345678');

    expect($second->id)->not->toBe($first->id);
    expect($this->sink->sent)->toHaveCount(2);
});

// ─── verify-otp ──────────────────────────────────────────────────────────────

it('verifies a correct otp and returns a jwt token', function (): void {
    $this->postJson('/api/auth/otp/request', [
        'type' => 'phone',
        'identifier' => '512345678',
    ])->assertOk();

    $code = $this->sink->lastCode();

    $response = $this->postJson('/api/auth/otp/verify', [
        'type' => 'phone',
        'identifier' => '512345678',
        'otp' => $code,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['status', 'message', 'data' => ['token', 'token_type', 'expires_in', 'user' => ['id', 'phone', 'role']]])
        ->assertJsonPath('data.token_type', 'bearer')
        ->assertJsonPath('data.user.phone', '512345678');

    expect(User::where('phone', '512345678')->first()->phone_verified_at)->not->toBeNull();
});

it('rejects an incorrect otp', function (): void {
    $this->postJson('/api/auth/otp/request', [
        'type' => 'phone',
        'identifier' => '512345678',
    ])->assertOk();

    $this->postJson('/api/auth/otp/verify', [
        'type' => 'phone',
        'identifier' => '512345678',
        'otp' => '000000',
    ])->assertStatus(422)->assertJsonPath('message', 'Invalid or expired OTP.');
});

it('rejects a used otp', function (): void {
    $this->postJson('/api/auth/otp/request', [
        'type' => 'phone',
        'identifier' => '512345678',
    ])->assertOk();

    $code = $this->sink->lastCode();

    $this->postJson('/api/auth/otp/verify', [
        'type' => 'phone',
        'identifier' => '512345678',
        'otp' => $code,
    ])->assertOk();

    $this->postJson('/api/auth/otp/verify', [
        'type' => 'phone',
        'identifier' => '512345678',
        'otp' => $code,
    ])->assertStatus(422);
});

it('rejects an expired otp', function (): void {
    $user = User::factory()->create(['phone' => '512345678']);

    Otp::create([
        'user_id' => $user->id,
        'type' => OtpType::Phone->value,
        'otp' => Hash::make('123456'),
        'attempts' => 0,
        'used' => false,
        'expires_at' => now()->subMinute(),
    ]);

    $this->postJson('/api/auth/otp/verify', [
        'type' => 'phone',
        'identifier' => '512345678',
        'otp' => '123456',
    ])->assertStatus(422);
});

it('locks the otp after 3 failed attempts and the correct code no longer works', function (): void {
    $user = User::factory()->create(['phone' => '512345678']);
    $service = app(OtpService::class);
    $service->issue($user, OtpType::Phone, '512345678');

    for ($i = 0; $i < 3; $i++) {
        expect($service->verify($user, OtpType::Phone, '000000'))->toBeFalse();
    }

    $realCode = $this->sink->lastCode();

    expect($service->verify($user, OtpType::Phone, $realCode))->toBeFalse();
});

it('issues a fresh otp after the previous one is locked from failed attempts', function (): void {
    $user = User::factory()->create(['phone' => '512345678']);
    $service = app(OtpService::class);
    $first = $service->issue($user, OtpType::Phone, '512345678');

    for ($i = 0; $i < 3; $i++) {
        $service->verify($user, OtpType::Phone, '000000');
    }

    $second = $service->issue($user, OtpType::Phone, '512345678');

    expect($second->id)->not->toBe($first->id);
    expect($this->sink->sent)->toHaveCount(2);
});

// ─── authenticated endpoints ─────────────────────────────────────────────────

it('returns the authenticated user from /auth/me', function (): void {
    $user = User::factory()->create(['phone' => '512345678'])->refresh();
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('rejects /auth/me without a token', function (): void {
    $this->getJson('/api/user')->assertStatus(401);
});

it('logs out and invalidates the token', function (): void {
    $user = User::factory()->create(['phone' => '512345678']);
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/auth/logout')
        ->assertOk();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/user')
        ->assertStatus(401);
});

it('refreshes a token', function (): void {
    $user = User::factory()->create(['phone' => '512345678']);
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/auth/refresh')
        ->assertOk()
        ->assertJsonStructure(['status', 'message', 'data' => ['token', 'token_type', 'expires_in']]);
});

it('puts the admin role in the jwt custom claims', function (): void {
    $admin = User::factory()->create([
        'phone' => '599999999',
        'role' => UserRole::Admin->value,
    ]);

    $token = auth('api')->login($admin);
    $payload = auth('api')->setToken($token)->payload();

    expect($payload->get('role'))->toBe('admin');
});
