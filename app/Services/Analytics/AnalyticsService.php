<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Server-side analytics capture — see docs/feature-analytics.md.
 *
 * Deliberately tiny: the behavioural events all come from the web/app SDKs.
 * This exists only for facts the client must not be trusted about, i.e. money.
 *
 * Fire-and-forget by contract: a short timeout and every failure swallowed, so
 * a slow or broken analytics endpoint can never delay a Moyasar webhook or roll
 * back a booking confirmation. Tracking is the first thing to sacrifice.
 */
final class AnalyticsService
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function capture(string $event, ?string $distinctId, array $properties = []): void
    {
        $key = (string) config('analytics.posthog.key');

        if ($key === '' || $distinctId === null) {
            return;
        }

        try {
            Http::timeout((int) config('analytics.posthog.timeout', 2))
                ->post(rtrim((string) config('analytics.posthog.host'), '/').'/capture/', [
                    'api_key' => $key,
                    'event' => $event,
                    'distinct_id' => $distinctId,
                    'properties' => [
                        'platform' => 'server',
                        ...array_filter($properties, fn ($v): bool => $v !== null && $v !== ''),
                    ],
                ]);
        } catch (Throwable $e) {
            // Never let analytics break the business transition that fired it.
            Log::warning('analytics: capture failed', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }
}
