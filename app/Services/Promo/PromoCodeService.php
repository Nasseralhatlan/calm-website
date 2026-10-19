<?php

declare(strict_types=1);

namespace App\Services\Promo;

use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\Place\SettingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Promo codes — Calm-funded discounts.
 *
 * Money rule: the discount comes off the stay (ex VAT) on the GUEST side
 * only. VAT is charged on (stay − discount); Calm's commission invoice to the
 * host and the host's payout are computed from the full stay exactly as
 * before. So Calm absorbs the whole discount out of its own commission:
 *
 *   Calm net (ex VAT) = commission_amount − discount_amount
 *
 * A code "exceeds Calm's percentage" when that can go negative — Calm then
 * pays out of its own pocket on top of giving up the commission.
 */
final class PromoCodeService
{
    /** Why a code was refused — clients map these to localized copy. */
    public const NOT_FOUND = 'not_found';

    public const INACTIVE = 'inactive';

    public const NOT_STARTED = 'not_started';

    public const EXPIRED = 'expired';

    public const EXHAUSTED = 'exhausted';

    public const ALREADY_USED = 'already_used';

    public const MIN_STAY = 'min_stay';

    private const DEFAULT_COMMISSION_PERCENTAGE = 15.0;

    public function __construct(private readonly SettingService $settings) {}

    /**
     * Resolve a guest-entered code against a stay. Returns the code and the
     * discount it gives, or the refusal reason. `$lock` takes a row lock on
     * the code (booking creation) so the last use can't be redeemed twice.
     *
     * @return array{ok: true, promo: PromoCode, discount_minor: int}|array{ok: false, reason: string, min_stay_minor?: int}
     */
    public function resolve(string $code, ?User $user, int $stayMinor, bool $lock = false): array
    {
        $query = PromoCode::query()->where('code', PromoCode::normalize($code));
        $promo = $lock ? $query->lockForUpdate()->first() : $query->first();

        if ($promo === null) {
            return ['ok' => false, 'reason' => self::NOT_FOUND];
        }
        if (! $promo->is_active) {
            return ['ok' => false, 'reason' => self::INACTIVE];
        }
        if ($promo->starts_at !== null && $promo->starts_at->isFuture()) {
            return ['ok' => false, 'reason' => self::NOT_STARTED];
        }
        if ($promo->ends_at !== null && $promo->ends_at->isPast()) {
            return ['ok' => false, 'reason' => self::EXPIRED];
        }
        if ($promo->min_stay_amount !== null && $stayMinor < $promo->min_stay_amount) {
            return ['ok' => false, 'reason' => self::MIN_STAY, 'min_stay_minor' => $promo->min_stay_amount];
        }
        if ($promo->max_uses !== null && $promo->redemptions()->count() >= $promo->max_uses) {
            return ['ok' => false, 'reason' => self::EXHAUSTED];
        }
        // Per-guest limit: checked whenever we know the guest. A signed-out
        // guest on the public quote gets the code provisionally — booking
        // creation (always signed in, after the login step) re-checks it.
        if ($promo->max_uses_per_user !== null && $user !== null
            && $promo->redemptions()->where('guest_user_id', $user->id)->count() >= $promo->max_uses_per_user) {
            return ['ok' => false, 'reason' => self::ALREADY_USED];
        }

        $discount = $promo->discountFor($stayMinor);
        if ($discount <= 0) {
            return ['ok' => false, 'reason' => self::MIN_STAY, 'min_stay_minor' => PromoCode::MIN_PAYABLE_STAY_MINOR + 1];
        }

        return ['ok' => true, 'promo' => $promo, 'discount_minor' => $discount];
    }

    /** English fallback copy for a refusal (API clients localize by `reason`). */
    public static function refusalMessage(string $reason): string
    {
        return match ($reason) {
            self::NOT_FOUND => 'This promo code does not exist.',
            self::INACTIVE, self::EXPIRED => 'This promo code has expired.',
            self::NOT_STARTED => 'This promo code is not active yet.',
            self::EXHAUSTED => 'This promo code has reached its usage limit.',
            self::ALREADY_USED => 'You have already used this promo code.',
            self::MIN_STAY => 'This stay does not meet the promo code minimum.',
            default => 'This promo code cannot be applied.',
        };
    }

    /** Calm's CURRENT commission rate — what new bookings will snapshot. */
    public function currentCommissionRate(): float
    {
        $value = $this->settings->byKeys(['commission_percentage'])['commission_percentage'] ?? null;

        return is_numeric($value) ? (float) $value : self::DEFAULT_COMMISSION_PERCENTAGE;
    }

    /**
     * Admin list: every code with its redemption stats and the money it has
     * cost Calm. `calm_loss_minor` is the part of the discounts that went
     * PAST the commission on those bookings (Calm paying from its pocket).
     *
     * @return Collection<int, PromoCode>
     */
    public function adminList(): Collection
    {
        $codes = PromoCode::query()->latest()->get();
        if ($codes->isEmpty()) {
            return $codes;
        }

        $stats = Booking::query()
            ->whereIn('promo_code_id', $codes->modelKeys())
            ->whereIn('booking_status', ['confirmed', 'completed'])
            ->get(['promo_code_id', 'discount_amount', 'commission_amount'])
            ->groupBy('promo_code_id');

        foreach ($codes as $code) {
            $rows = $stats->get($code->id, collect());
            $code->setAttribute('paid_uses', $rows->count());
            $code->setAttribute('discount_given_minor', (int) $rows->sum('discount_amount'));
            $code->setAttribute('calm_loss_minor', (int) $rows->sum(
                fn (Booking $b): int => max(0, (int) $b->discount_amount - (int) $b->commission_amount),
            ));
        }

        return $codes;
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): PromoCode
    {
        return PromoCode::query()->create($this->prepare($data));
    }

    /** @param  array<string, mixed>  $data */
    public function update(PromoCode $promo, array $data): PromoCode
    {
        $promo->update($this->prepare($data));

        return $promo;
    }

    /**
     * Unused codes delete; a code bookings already used is only deactivated —
     * those bookings keep their snapshot either way, but the admin list keeps
     * its history.
     */
    public function delete(PromoCode $promo): bool
    {
        if ($promo->bookings()->exists()) {
            $promo->update(['is_active' => false]);

            return false;
        }

        $promo->delete();

        return true;
    }

    /**
     * Form input → columns: normalized code, SAR fields → halalas.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(array $data): array
    {
        $toMinor = fn (mixed $sar): ?int => $sar === null || $sar === '' ? null : (int) round((float) $sar * 100);

        return [
            'code' => PromoCode::normalize((string) $data['code']),
            'discount_type' => $data['discount_type'],
            'discount_value' => (float) $data['discount_value'],
            'max_discount_amount' => $toMinor($data['max_discount_sar'] ?? null),
            'min_stay_amount' => $toMinor($data['min_stay_sar'] ?? null),
            'max_uses' => $data['max_uses'] ?? null,
            'max_uses_per_user' => $data['max_uses_per_user'] ?? null,
            // Admin picks calendar days in Riyadh time: valid from the start
            // of the first day through the end of the last one.
            'starts_at' => $this->riyadhDay($data['starts_at'] ?? null)?->startOfDay()->utc(),
            'ends_at' => $this->riyadhDay($data['ends_at'] ?? null)?->endOfDay()->utc(),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'note' => $data['note'] ?? null,
        ];
    }

    private function riyadhDay(mixed $date): ?CarbonImmutable
    {
        return is_string($date) && $date !== '' ? CarbonImmutable::parse($date, 'Asia/Riyadh') : null;
    }
}
