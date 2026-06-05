<?php

declare(strict_types=1);

namespace App\Enums;

enum OtpType: string
{
    case Email = 'email';
    case Phone = 'phone';
}
