<?php

declare(strict_types=1);

use App\Contracts\SmsDeliveryContract;
use App\Enums\UserRole;
use App\Models\User;
use Tests\Support\TestSmsDelivery;

beforeEach(function (): void {
    $this->sink = new TestSmsDelivery;
    $this->app->instance(SmsDeliveryContract::class, $this->sink);
});

// ─── pages render ────────────────────────────────────────────────────────────

it('renders the login page', function (): void {
    $this->get('/login')->assertOk()->assertSee('Calm', escape: false);
});

it('redirects authenticated users away from /login (guest middleware)', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user, 'api')->get('/login')->assertRedirect();
});

// ─── request-otp ─────────────────────────────────────────────────────────────

it('requests an otp and redirects to verify', function (): void {
    $response = $this->post('/login', ['phone' => '512345678']);

    $response->assertRedirect('/login/verify?phone=512345678');
    expect(User::where('phone', '512345678')->exists())->toBeTrue();
    expect($this->sink->sent)->toHaveCount(1);
});

it('rejects invalid phone format on login form', function (): void {
    $this->post('/login', ['phone' => '0512345678'])
        ->assertRedirect()
        ->assertSessionHasErrors('phone');
});

// ─── verify-otp + role-based redirect ────────────────────────────────────────

it('signs in a regular user and redirects to /profile', function (): void {
    $this->post('/login', ['phone' => '512345678']);
    $code = $this->sink->lastCode();

    $response = $this->post('/login/verify', ['phone' => '512345678', 'otp' => $code]);

    $response->assertRedirect('/profile');

    // The JWT cookie must be set on the response with a non-empty value.
    $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === 'calm_token');
    expect($cookie)->not->toBeNull();
    expect($cookie->getValue())->not->toBeEmpty();
});

it('signs in an admin and redirects to /admin', function (): void {
    User::factory()->create([
        'phone' => '599999999',
        'role' => UserRole::Admin->value,
    ]);

    $this->post('/login', ['phone' => '599999999']);
    $code = $this->sink->lastCode();

    $this->post('/login/verify', ['phone' => '599999999', 'otp' => $code])
        ->assertRedirect('/admin');
});

it('rejects a bad otp on verify', function (): void {
    $this->post('/login', ['phone' => '512345678']);

    $response = $this->post('/login/verify', ['phone' => '512345678', 'otp' => '000000']);

    $response->assertRedirect()->assertSessionHasErrors('otp');

    // No JWT cookie should be set on a failed verify.
    $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === 'calm_token');
    expect($cookie)->toBeNull();
});

// ─── logout ──────────────────────────────────────────────────────────────────

it('logs out and clears the jwt cookie', function (): void {
    $user = User::factory()->create(['phone' => '512345678']);
    $this->actingAs($user, 'api');

    $response = $this->post('/logout');
    $response->assertRedirect('/');

    // Logout response carries a forget-cookie for calm_token (empty value, past expiry).
    $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === 'calm_token');
    expect($cookie)->not->toBeNull();
    expect($cookie->getValue())->toBeEmpty();
});

// ─── route protection ────────────────────────────────────────────────────────

it('redirects guests from /admin to /login', function (): void {
    $this->get('/admin')->assertRedirect('/login');
});

it('redirects non-admin authenticated users from /admin to /profile', function (): void {
    $user = User::factory()->create(['role' => UserRole::User->value]);
    $this->actingAs($user, 'api')->get('/admin')->assertRedirect('/profile');
});

it('lets admins into /admin', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value]);
    $this->actingAs($admin, 'api')->get('/admin')->assertOk();
});

it('redirects guests from /profile to /login', function (): void {
    $this->get('/profile')->assertRedirect('/login');
});

it('lets any authenticated user see /profile', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user, 'api')->get('/profile')->assertOk();
});
