<?php

declare(strict_types=1);

namespace App\Enums;

enum AttributeType: string
{
    case Number = 'number';
    case Text = 'text';
    case Select = 'select';
    case MultiSelect = 'multi_select';

    /**
     * Presence-only flag (e.g. WiFi, TV, PlayStation).
     * Storage convention: a row in `place_attributes` exists IFF the host
     * selected the chip. We never store `false` — absence is "no".
     * The host UI renders all `Boolean` attributes in a group as a chip cloud.
     */
    case Boolean = 'boolean';

    /**
     * Whether this type needs the `options` array on the Attribute record.
     */
    public function hasOptions(): bool
    {
        return $this === self::Select || $this === self::MultiSelect;
    }
}
