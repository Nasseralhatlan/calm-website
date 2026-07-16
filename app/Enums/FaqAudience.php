<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who an FAQ entry addresses. The public /faq page renders one tab per case.
 */
enum FaqAudience: string
{
    case Guest = 'guest';
    case Host = 'host';
}
