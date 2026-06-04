<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Single home for the Saudi mobile-number rule.
 * Saudi mobile numbers are 9 digits, start with 5, and exclude the +966 prefix
 * since the UI provides that as a fixed widget.
 */
trait HasSaudiPhoneRule
{
    /**
     * @return array<int, string>
     */
    protected function saudiPhoneRule(): array
    {
        return ['required', 'string', 'regex:/^5\d{8}$/'];
    }
}
