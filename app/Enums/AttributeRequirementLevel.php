<?php

declare(strict_types=1);

namespace App\Enums;

enum AttributeRequirementLevel: string
{
    case Required = 'required';
    case Recommended = 'recommended';
    case Optional = 'optional';
}
