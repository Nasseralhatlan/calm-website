<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PromoDiscountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An admin-created discount code. Calm-funded: the discount comes out of
 * Calm's commission, never the host's payout — see PromoCodeService.
 */
class PromoCode extends Model
{
    use HasUuids;

    /**
     * The guest always pays at least this much of the stay (ex VAT), so a
     * 100% / oversized fixed code can never produce a zero-amount payment
     * the gateway would reject.
     */
    public const MIN_PAYABLE_STAY_MINOR = 100;

    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_stay_amount',
        'max_uses',
        'max_uses_per_user',
        'starts_at',
        'ends_at',
        'is_active',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'discount_type' => PromoDiscountType::class,
            'discount_value' => 'float',
            'max_discount_amount' => 'integer',
            'min_stay_amount' => 'integer',
            'max_uses' => 'integer',
            'max_uses_per_user' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** Codes are case-insensitive for guests; we store and match UPPERCASE. */
    public static function normalize(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Bookings that hold a redemption: paid ones, plus live unpaid holds (so
     * two guests can't race onto the last use). Expired holds and cancelled
     * bookings release their use.
     *
     * @return HasMany<Booking, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->bookings()->where(function (Builder $q): void {
            $q->whereIn('booking_status', [BookingStatus::Confirmed->value, BookingStatus::Completed->value])
                ->orWhere(function (Builder $q): void {
                    $q->where('booking_status', BookingStatus::PendingPayment->value)
                        ->where('expires_at', '>', now());
                });
        });
    }

    /**
     * The ex-VAT discount (halalas) this code gives on a stay of $stayMinor:
     * percent or fixed, then the optional cap, then never below the minimum
     * payable stay.
     */
    public function discountFor(int $stayMinor): int
    {
        $discount = $this->discount_type === PromoDiscountType::Percentage
            ? (int) round($stayMinor * $this->discount_value / 100)
            : (int) round($this->discount_value * 100);

        if ($this->max_discount_amount !== null) {
            $discount = min($discount, $this->max_discount_amount);
        }

        return max(0, min($discount, $stayMinor - self::MIN_PAYABLE_STAY_MINOR));
    }

    /**
     * Does this code eat past Calm's own commission? Percentage codes compare
     * directly; fixed codes lose money on any stay below the break-even
     * value (amount ÷ commission rate) — unless the minimum stay already
     * keeps every redemption above it.
     *
     * @return array{exceeds: bool, over_by_rate: float|null, break_even_stay_minor: int|null}
     */
    public function commissionCheck(float $commissionRate): array
    {
        if ($this->discount_type === PromoDiscountType::Percentage) {
            $over = round($this->discount_value - $commissionRate, 2);

            return ['exceeds' => $over > 0, 'over_by_rate' => $over > 0 ? $over : null, 'break_even_stay_minor' => null];
        }

        if ($commissionRate <= 0) {
            return ['exceeds' => $this->discount_value > 0, 'over_by_rate' => null, 'break_even_stay_minor' => null];
        }

        $breakEven = (int) ceil($this->discount_value * 100 * 100 / $commissionRate);
        $exceeds = $this->min_stay_amount === null || $this->min_stay_amount < $breakEven;

        return ['exceeds' => $exceeds, 'over_by_rate' => null, 'break_even_stay_minor' => $breakEven];
    }

    /** Human label for lists: "15%" or "SR 100". */
    public function label(): string
    {
        $value = rtrim(rtrim(number_format($this->discount_value, 2, '.', ''), '0'), '.');

        return $this->discount_type === PromoDiscountType::Percentage ? "{$value}%" : "SR {$value}";
    }
}
