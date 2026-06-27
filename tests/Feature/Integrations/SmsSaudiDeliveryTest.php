<?php

declare(strict_types=1);

use App\Exceptions\SmsDeliveryException;
use App\Integrations\Sms\SmsSaudiDelivery;
use Illuminate\Support\Facades\Http;

function makeSmsSaudi(): SmsSaudiDelivery
{
    return new SmsSaudiDelivery(
        endpoint: 'https://api-server14.com/api/send.aspx',
        apiKey: 'TESTKEY',
        sender: 'AslahBles',
        countryCode: '966',
        language: '1',
        timeout: 5,
    );
}

it('sends an OK request with the right query string shape', function (): void {
    Http::fake([
        'api-server14.com/*' => Http::response('OK,smsid:4-89161e306,mobiles:30,time:05/07/2026 17:29:48'),
    ]);

    makeSmsSaudi()->send('512345678', 'Your Calm code is: 123456');

    Http::assertSent(function ($request) {
        return $request->method() === 'GET'
            && str_contains($request->url(), 'api-server14.com/api/send.aspx')
            && $request['apikey'] === 'TESTKEY'
            && $request['language'] === '1'
            && $request['sender'] === 'AslahBles'
            && $request['mobile'] === '966512345678'  // country code prepended
            && $request['message'] === 'Your Calm code is: 123456';
    });
});

it('tags Arabic messages with language=2 so the gateway encodes them correctly', function (): void {
    Http::fake([
        'api-server14.com/*' => Http::response('OK,smsid:4-abc,mobiles:1,time:...'),
    ]);

    // Arabic body (letters + Arabic-Indic digits) — even though the driver's
    // configured default is '1' (English), the content drives the flag.
    makeSmsSaudi()->send('512345678', 'تم تأكيد حجزك. رقم الحجز: ١٢٣');

    Http::assertSent(fn ($request) => $request['language'] === '2');
});

it('does not double-prefix the country code', function (): void {
    Http::fake([
        'api-server14.com/*' => Http::response('OK,smsid:1-abc,mobiles:1,time:...'),
    ]);

    makeSmsSaudi()->send('966512345678', 'msg');

    Http::assertSent(fn ($request) => $request['mobile'] === '966512345678');
});

it('throws SmsDeliveryException when the gateway returns "error,..."', function (): void {
    Http::fake([
        'api-server14.com/*' => Http::response('error,invalid login'),
    ]);

    makeSmsSaudi()->send('512345678', 'msg');
})->throws(SmsDeliveryException::class, 'invalid login');

it('throws SmsDeliveryException when the network call fails', function (): void {
    Http::fake([
        'api-server14.com/*' => fn () => throw new RuntimeException('Connection refused'),
    ]);

    makeSmsSaudi()->send('512345678', 'msg');
})->throws(SmsDeliveryException::class, 'Connection refused');
