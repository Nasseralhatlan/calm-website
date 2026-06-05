<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether an attribute's value is accompanied by a photo on the host's place form.
 *
 * - None     → no photo field rendered at all (the default for most amenities)
 * - Optional → photo upload offered but not required
 * - Required → host must upload a photo to fill the attribute in
 */
enum AttributePhotoRule: string
{
    case None = 'none';
    case Optional = 'optional';
    case Required = 'required';
}
