<?php

declare(strict_types=1);

use App\Contracts\SmsDeliveryContract;
use App\Enums\OtpType;
use App\Integrations\Sms\RoutingSmsDelivery;
use App\Integrations\Sms\SmsSaudiDelivery;
use App\Models\User;
use App\Services\Otp\OtpService;
use App\Support\MockPhoneRegistry;
use Tests\Support\TestSmsDelivery;

it('routes whitelisted phones to the mock and others to the primary', function (): void {
    $primary = new TestSmsDelivery;
    $mock = new TestSmsDelivery;
    $router = new RoutingSmsDelivery($primary, $mock, new MockPhoneRegistry(['501234567']));

    $router->send('501234567', 'hello tester');   // whitelisted → mock
    $router->send('509999999', 'hello real');      // not listed → primary

    expect($mock->sent)->toHaveCount(1)
        ->and($mock->sent[0]['phone'])->toBe('501234567')
        ->and($primary->sent)->toHaveCount(1)
        ->and($primary->sent[0]['phone'])->toBe('509999999');
});

it('matches a phone regardless of 0 / +966 / 966 formatting', function (): void {
    $registry = new MockPhoneRegistry(['501234567']);

    expect($registry->has('501234567'))->toBeTrue()
        ->and($registry->has('0501234567'))->toBeTrue()
        ->and($registry->has('+966501234567'))->toBeTrue()
        ->and($registry->has('966501234567'))->toBeTrue()
        ->and($registry->has('509999999'))->toBeFalse();

    expect((new MockPhoneRegistry([]))->isEmpty())->toBeTrue();
});

it('binds a routing delivery only when the list is set and the driver is real', function (): void {
    config([
        'sms.driver' => 'sms_saudi',
        'sms.sms_saudi.api_key' => 'k',
        'sms.sms_saudi.sender' => 's',
    ]);

    // No whitelist → plain real driver.
    config(['sms.mock_phones' => []]);
    expect(app(SmsDeliveryContract::class))->toBeInstanceOf(SmsSaudiDelivery::class);

    // Whitelist present → wrapped in the router.
    config(['sms.mock_phones' => ['501234567']]);
    expect(app(SmsDeliveryContract::class))->toBeInstanceOf(RoutingSmsDelivery::class);
});

it('issues the fixed code 111111 to a whitelisted phone even on the real driver', function (): void {
    config(['sms.driver' => 'sms_saudi', 'sms.mock_phones' => ['501234567']]);
    app()->instance(SmsDeliveryContract::class, new TestSmsDelivery); // avoid real gateway

    $user = User::factory()->create(['phone' => '501234567']);
    app(OtpService::class)->issue($user, OtpType::Phone, '501234567');

    expect(app(OtpService::class)->verify($user, OtpType::Phone, '111111'))->toBeTrue();
});

it('issues a random code to a non-whitelisted phone on the real driver', function (): void {
    config(['sms.driver' => 'sms_saudi', 'sms.mock_phones' => ['501234567']]);
    app()->instance(SmsDeliveryContract::class, new TestSmsDelivery);

    $user = User::factory()->create(['phone' => '509999999']);
    app(OtpService::class)->issue($user, OtpType::Phone, '509999999');

    // Not the fixed code (random) — astronomically unlikely to be 111111.
    expect(app(OtpService::class)->verify($user, OtpType::Phone, '111111'))->toBeFalse();
});
