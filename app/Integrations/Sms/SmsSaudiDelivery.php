<?php

declare(strict_types=1);

namespace App\Integrations\Sms;

use App\Contracts\SmsDeliveryContract;
use App\Exceptions\SmsDeliveryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Real SMS delivery via sms-saudi.com (api-server14.com).
 *
 * Endpoint:
 *   GET https://api-server14.com/api/send.aspx
 *     ?apikey=XXX&language=1&sender=NAME&mobile=9665XXXXXXXX&message=...
 *
 * Success response (HTTP 200):  OK,smsid:4-XXXXXXXXX,mobiles:30,time:...
 * Error response   (HTTP 200):  error,<reason>
 *
 * Notes:
 *  - Phones are stored locally as "5xxxxxxxx" (9 digits). The gateway needs
 *    the full international form, so we prepend the configured country code.
 *  - The gateway returns 200 for both success and error — we MUST look at the
 *    body, not the status code.
 */
final class SmsSaudiDelivery implements SmsDeliveryContract
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $apiKey,
        private readonly string $sender,
        private readonly string $countryCode,
        private readonly string $language,
        private readonly int $timeout,
    ) {}

    public function send(string $phone, string $message): void
    {
        $internationalPhone = $this->internationalize($phone);

        try {
            $response = Http::timeout($this->timeout)
                ->asForm()
                ->get($this->endpoint, [
                    'apikey' => $this->apiKey,
                    'language' => $this->language,
                    'sender' => $this->sender,
                    'mobile' => $internationalPhone,
                    'message' => $message,
                ]);
        } catch (Throwable $e) {
            throw new SmsDeliveryException(
                'SMS gateway request failed: '.$e->getMessage(),
                previous: $e,
            );
        }

        $body = trim((string) $response->body());

        if (! str_starts_with($body, 'OK')) {
            throw new SmsDeliveryException("SMS gateway rejected the message: {$body}");
        }

        Log::info('[SMS] dispatched via sms-saudi', [
            'mobile' => $internationalPhone,
            'response' => $body,
        ]);
    }

    /**
     * Convert a locally-stored phone (e.g. "512345678") into the wire format
     * the gateway expects ("966512345678"). Idempotent — already-international
     * numbers pass through.
     */
    private function internationalize(string $phone): string
    {
        $phone = ltrim($phone, '+');

        if (str_starts_with($phone, $this->countryCode)) {
            return $phone;
        }

        return $this->countryCode.$phone;
    }
}
