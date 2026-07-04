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
        // Belt-and-braces: any row the 000001 backfill somehow missed gets its
        // snapshot completed from the legacy columns before they disappear.
        // (Same mapping as 000001; commission VAT 0 preserves agreed payouts.)
        DB::table('bookings')
            ->where('host_gross_amount', 0)
            ->where('booking_amount', '>', 0)
            ->update([
                'host_gross_amount' => DB::raw('booking_amount'),
                'guest_vat_rate' => DB::raw('vat_rate'),
                'guest_vat_amount' => DB::raw('vat_amount'),
                'guest_total' => DB::raw('total'),
                'commission_amount_ex_vat' => DB::raw('commission_amount'),
                'commission_vat_rate' => 0,
                'commission_vat_amount' => 0,
                'commission_total' => DB::raw('commission_amount'),
                'host_payout_amount' => DB::raw('booking_amount - commission_amount'),
            ]);

        // The finance snapshot columns are now the single money model —
        // the legacy pre-finance columns duplicated them one-for-one.
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['booking_amount', 'commission_amount', 'vat_rate', 'vat_amount', 'total']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedBigInteger('booking_amount')->default(0);
            $table->unsignedBigInteger('commission_amount')->default(0);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('vat_amount')->default(0);
            $table->unsignedBigInteger('total')->default(0);
        });

        DB::table('bookings')->update([
            'booking_amount' => DB::raw('host_gross_amount'),
            'commission_amount' => DB::raw('commission_amount_ex_vat'),
            'vat_rate' => DB::raw('guest_vat_rate'),
            'vat_amount' => DB::raw('guest_vat_amount'),
            'total' => DB::raw('guest_total'),
        ]);
    }
};
