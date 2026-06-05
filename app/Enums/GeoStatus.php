<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle flag for geo records (countries + cities). Starts as a simple
 * Active/Inactive split so the login country picker and the host wizard can
 * scope to only the rows we're ready to serve, but the enum can expand later
 * (e.g. Maintenance, Archived) without a column migration.
 */
enum GeoStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
