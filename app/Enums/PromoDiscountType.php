<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a promo code's discount_value reads: a percent off the stay, or a fixed
 * SAR amount off the stay. Both are ex-VAT and Calm-funded.
 */
enum PromoDiscountType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}
