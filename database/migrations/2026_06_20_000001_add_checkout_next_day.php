<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('places', 'checkout_next_day')) {
            Schema::table('places', function (Blueprint $table): void {
                // Whether checkout falls the morning AFTER the booking ends
                // (overnight) vs the same day (day-use). Explicit, not inferred.
                $table->boolean('checkout_next_day')->default(true)->after('check_out_time');
            });
        }

        if (! Schema::hasColumn('bookings', 'checkout_next_day')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->boolean('checkout_next_day')->default(true)->after('check_out_time');
            });
        }

        // Backfill from the old inference (next day when checkout time is at or
        // before check-in time) so existing rows keep their current behavior.
        $minutes = static function (?string $time): ?int {
            if ($time === null || $time === '') {
                return null;
            }
            [$h, $m] = array_pad(explode(':', $time), 2, '0');

            return (int) $h * 60 + (int) $m;
        };

        foreach (['places', 'bookings'] as $table) {
            DB::table($table)
                ->select('id', 'check_in_time', 'check_out_time')
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($table, $minutes): void {
                    foreach ($rows as $row) {
                        $in = $minutes($row->check_in_time);
                        $out = $minutes($row->check_out_time);
                        // Overnight (next day) when checkout time <= check-in time,
                        // or when either time is unknown (default overnight).
                        $nextDay = $in === null || $out === null ? true : $out <= $in;

                        DB::table($table)->where('id', $row->id)->update(['checkout_next_day' => $nextDay]);
                    }
                });
        }
    }

    public function down(): void
    {
        foreach (['places', 'bookings'] as $table) {
            if (Schema::hasColumn($table, 'checkout_next_day')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropColumn('checkout_next_day');
                });
            }
        }
    }
};
