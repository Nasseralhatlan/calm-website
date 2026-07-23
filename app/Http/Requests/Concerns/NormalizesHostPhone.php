<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Admins type owner phones the way humans do — "0512345678",
 * "+966 51 234 5678", "966512345678" — while the system stores the
 * 9-digit national form (5XXXXXXXX). Normalize before validation so
 * the strict regex only ever rejects genuinely wrong numbers.
 */
trait NormalizesHostPhone
{
    protected function normalizeHostPhone(): void
    {
        $raw = $this->input('host_phone');

        if (! is_string($raw) || trim($raw) === '') {
            return;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        $digits = preg_replace('/^(?:00966|966)/', '', $digits) ?? '';
        $digits = ltrim($digits, '0');

        $this->merge(['host_phone' => $digits]);
    }
}
