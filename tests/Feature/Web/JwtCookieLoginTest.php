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

it('sets the calm_token httpOnly cookie on successful web login', function (): void {
    User::factory()->create(['phone' => '500000099', 'role' => UserRole::Admin->value]);

    $this->post('/login', ['phone' => '500000099']);
    $code = $this->sink->lastCode();

    $response = $this->post('/login/verify', ['phone' => '500000099', 'otp' => $code]);
    $response->assertRedirect('/admin');

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($c) => $c->getName() === 'calm_token');

    expect($cookie)->not->toBeNull();
    expect($cookie->getValue())->not->toBeEmpty();
    expect($cookie->isHttpOnly())->toBeTrue();
});

it('lets the same JWT cookie authenticate a subsequent SSR request to /admin', function (): void {
    User::factory()->create(['phone' => '500000099', 'role' => UserRole::Admin->value]);

    $this->post('/login', ['phone' => '500000099']);
    $code = $this->sink->lastCode();

    $loginResponse = $this->post('/login/verify', ['phone' => '500000099', 'otp' => $code]);
    $jwtCookie = collect($loginResponse->headers->getCookies())
        ->first(fn ($c) => $c->getName() === 'calm_token');

    // Carrying just that cookie (no Authorization header) — server-side render must work.
    // `withUnencryptedCookie` matches how the browser sends the raw JWT to the server
    // (Laravel's EncryptCookies middleware skips calm_token via the except list).
    $this->withUnencryptedCookie('calm_token', $jwtCookie->getValue())
        ->get('/admin')
        ->assertOk();
});

it('clears the calm_token cookie on logout', function (): void {
    $user = User::factory()->create(['phone' => '500000099']);
    $this->actingAs($user, 'api');

    $response = $this->post('/logout');
    $response->assertRedirect('/');

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($c) => $c->getName() === 'calm_token');

    expect($cookie)->not->toBeNull();
    expect($cookie->getValue())->toBeEmpty();
});
