<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\SmsDeliveryContract;
use App\Integrations\Sms\MockSmsDelivery;
use App\Integrations\Sms\RoutingSmsDelivery;
use App\Integrations\Sms\SmsSaudiDelivery;
use App\Support\MockPhoneRegistry;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class OtpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsDeliveryContract::class, function (): SmsDeliveryContract {
            $primary = match (config('sms.driver')) {
                'sms_saudi' => $this->makeSmsSaudi(),
                'mock', null => $this->makeMock(),
                default => throw new RuntimeException(
                    'Unknown SMS driver: '.((string) config('sms.driver')),
                ),
            };

            // Route whitelisted phones to the mock driver. No-op when the list
            // is empty or the primary is already the mock.
            $registry = new MockPhoneRegistry;
            if ($registry->isEmpty() || $primary instanceof MockSmsDelivery) {
                return $primary;
            }

            return new RoutingSmsDelivery($primary, new MockSmsDelivery, $registry);
        });
    }

    /**
     * The mock driver is for local/CI only. In production it would mean real
     * users receive the fixed test OTP and no SMS is ever sent — an auth
     * bypass — so refuse to bind it and fail loudly instead. Whitelisted
     * tester/reviewer numbers are unaffected: they ride the real driver and are
     * routed to the mock per-recipient via RoutingSmsDelivery + SMS_MOCK_PHONES.
     */
    private function makeMock(): MockSmsDelivery
    {
        if ($this->app->isProduction()) {
            throw new RuntimeException(
                'SMS_DRIVER is "mock"/unset in production — refusing to bind it '
                .'(it would issue the fixed OTP to every user). Set SMS_DRIVER=sms_saudi; '
                .'tester numbers still work via SMS_MOCK_PHONES.',
            );
        }

        return new MockSmsDelivery;
    }

    private function makeSmsSaudi(): SmsSaudiDelivery
    {
        $config = config('sms.sms_saudi');

        foreach (['api_key', 'sender'] as $required) {
            if (empty($config[$required])) {
                throw new RuntimeException("sms.sms_saudi.{$required} is not configured.");
            }
        }

        return new SmsSaudiDelivery(
            endpoint: (string) $config['endpoint'],
            apiKey: (string) $config['api_key'],
            sender: (string) $config['sender'],
            countryCode: (string) $config['country_code'],
            language: (string) $config['language'],
            timeout: (int) $config['timeout'],
        );
    }
}
