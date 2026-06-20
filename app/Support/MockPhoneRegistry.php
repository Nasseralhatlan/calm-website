<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The set of phone numbers that should always use the mock SMS path (fixed OTP
 * + logged, never the real gateway). Sourced from `config('sms.mock_phones')`.
 *
 * Matching is on the last 9 digits, so the stored "5xxxxxxxx" form and any
 * "0…" / "+966…" / "966…" entry in the env all resolve to the same key.
 */
final class MockPhoneRegistry
{
    /** @var list<string> normalized (last-9-digit) phone keys */
    private array $phones;

    /**
     * @param  list<string>|null  $phones  Defaults to config('sms.mock_phones').
     */
    public function __construct(?array $phones = null)
    {
        $source = $phones ?? config('sms.mock_phones', []);

        $this->phones = array_values(array_filter(array_map(
            fn ($phone): string => $this->normalize((string) $phone),
            $source,
        )));
    }

    public function has(string $phone): bool
    {
        $key = $this->normalize($phone);

        return $key !== '' && in_array($key, $this->phones, true);
    }

    public function isEmpty(): bool
    {
        return $this->phones === [];
    }

    private function normalize(string $phone): string
    {
        return substr(preg_replace('/\D/', '', $phone) ?? '', -9);
    }
}
