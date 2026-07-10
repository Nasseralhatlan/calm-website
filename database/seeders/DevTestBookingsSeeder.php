<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Place;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Dev-only: seed a rich spread of bookings for one user (guest == host) so the
 * bookings list, booking detail, and host earnings screens have data to show.
 *
 * For each place the user hosts, creates 8 bookings: a Friday-priced set and a
 * base-priced set, each covering {expired, completed, today, upcoming}. Amounts
 * are computed from the place's real per-day pricing (price_friday vs base),
 * mirroring the app's PlaceAvailabilityService::dayPrice() rule.
 *
 * NOT wired into DatabaseSeeder. Run explicitly:
 *   php artisan db:seed --class=DevTestBookingsSeeder
 */
final class DevTestBookingsSeeder extends Seeder
{
    private const PHONE = '501203845';

    private const COMMISSION_RATE = 10.0;

    private const VAT_RATE = 15.0;

    public function run(): void
    {
        $user = User::query()->where('phone', self::PHONE)->first();

        if ($user === null) {
            $this->command->error('User '.self::PHONE.' not found — sign in once, then re-run.');

            return;
        }

        // Re-runnable: clear previously-seeded bookings across ALL the host's
        // places — including drafts an earlier (unfiltered) run polluted.
        $allPlaceIds = Place::query()->where('host_user_id', $user->id)->pluck('id');
        Booking::query()->whereIn('place_id', $allPlaceIds)->delete();

        // Only seed bookings into publishable listings — a draft/pending/
        // rejected place can never be booked for real (the quote 404s it),
        // so test bookings on one are pure noise.
        $places = Place::query()->where('host_user_id', $user->id)->visible()->get();

        if ($places->isEmpty()) {
            $this->command->error('User '.self::PHONE.' hosts no active+approved places.');

            return;
        }

        // [set, state, start_date, end_date, status, confirmed_at, expires_at]
        // Dates are anchored to "today" = Mon 2026-06-29 (see plan). The two
        // "today" rows are ongoing/active stays; the Friday one spans last
        // Friday → mid-week so it includes a Friday night while covering today.
        $rows = [
            // ── Friday-priced set (each stay includes a Friday night) ──
            ['fri',  'expired',   '2026-06-12', '2026-06-13', BookingStatus::Expired,   null,         '2026-06-12 12:10:00'],
            ['fri',  'completed', '2026-06-26', '2026-06-27', BookingStatus::Completed, '2026-06-20 09:00:00', null],
            ['fri',  'today',     '2026-06-26', '2026-07-01', BookingStatus::Confirmed, '2026-06-25 09:00:00', null],
            ['fri',  'upcoming',  '2026-07-03', '2026-07-04', BookingStatus::Confirmed, '2026-06-28 09:00:00', null],
            // ── Base-priced set (no Friday night) ──
            ['base', 'expired',   '2026-06-21', '2026-06-22', BookingStatus::Expired,   null,         '2026-06-21 12:10:00'],
            ['base', 'completed', '2026-06-14', '2026-06-15', BookingStatus::Completed, '2026-06-10 09:00:00', null],
            ['base', 'today',     '2026-06-29', '2026-06-30', BookingStatus::Confirmed, '2026-06-28 09:00:00', null],
            ['base', 'upcoming',  '2026-07-01', '2026-07-02', BookingStatus::Confirmed, '2026-06-28 09:00:00', null],
        ];

        $created = 0;

        foreach ($places as $place) {
            foreach ($rows as [$set, $state, $start, $end, $status, $confirmedAt, $expiresAt]) {
                [$nights, $subtotalMinor] = $this->priceStay($place, $start, $end);

                $commissionMinor = (int) round($subtotalMinor * self::COMMISSION_RATE / 100);
                $vatMinor = (int) round($subtotalMinor * self::VAT_RATE / 100);

                Booking::query()->create([
                    'place_id' => $place->id,
                    'guest_user_id' => $user->id,
                    'host_user_id' => $user->id,
                    'booking_status' => $status->value,
                    'start_date' => $start,
                    'end_date' => $end,
                    'check_in_time' => $place->check_in_time,
                    'check_out_time' => $place->check_out_time,
                    'checkout_next_day' => $place->checkout_next_day,
                    'rules' => $place->rules,
                    'guests' => min(2, (int) $place->max_guests ?: 1),
                    'nights' => $nights,
                    'stay_amount' => $subtotalMinor,
                    'commission_rate' => self::COMMISSION_RATE,
                    'commission_amount' => $commissionMinor,
                    'vat_rate' => self::VAT_RATE,
                    'vat_amount' => $vatMinor,
                    'total_amount' => $subtotalMinor + $vatMinor,
                    'payout_status' => 'not_paid',
                    'confirmed_at' => $confirmedAt,
                    'expires_at' => $expiresAt,
                ]);

                $created++;
            }
        }

        $this->command->info("Seeded {$created} bookings across {$places->count()} places for ".self::PHONE.'.');
    }

    /**
     * Sum each night's price over [start, end] INCLUSIVE — end_date is the
     * last occupied night, exactly like the live quote (`days = diff + 1`)
     * and every availability surface. Per-day rate (price_friday etc.) falls
     * back to base when a day column is 0/null, same rule as the quote.
     * Returns [nights, subtotal_in_halalas].
     *
     * @return array{0: int, 1: int}
     */
    private function priceStay(Place $place, string $start, string $end): array
    {
        $night = CarbonImmutable::parse($start);
        $lastNight = CarbonImmutable::parse($end);

        $nights = 0;
        $subtotal = 0;

        while ($night->lte($lastNight)) {
            $column = Place::PRICE_COLUMNS[strtolower($night->format('l'))];
            $rate = (int) ($place->{$column} ?: $place->price);
            $subtotal += $rate;
            $nights++;
            $night = $night->addDay();
        }

        return [$nights, $subtotal * 100];
    }
}
