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
                'mock', null => new MockSmsDelivery,
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
