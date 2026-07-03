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
        Schema::table('bookings', function (Blueprint $table): void {
            // ── Guest side (numbers on the Calm → Guest invoice) ──
            $table->decimal('guest_vat_rate', 8, 2)->default(15.00)->after('total');
            $table->unsignedBigInteger('guest_vat_amount')->default(0)->after('guest_vat_rate');
            $table->unsignedBigInteger('guest_total')->default(0)->after('guest_vat_amount');
            // Optional Calm service fee to the guest (0 until productized).
            $table->unsignedBigInteger('guest_service_fee_amount')->default(0)->after('guest_total');
            $table->unsignedBigInteger('guest_service_fee_vat_amount')->default(0)->after('guest_service_fee_amount');

            // ── Commission side (numbers on the Calm → Host invoice) ──
            $table->unsignedBigInteger('commission_amount_ex_vat')->default(0)->after('guest_service_fee_vat_amount');
            $table->decimal('commission_vat_rate', 8, 2)->default(15.00)->after('commission_amount_ex_vat');
            $table->unsignedBigInteger('commission_vat_amount')->default(0)->after('commission_vat_rate');
            $table->unsignedBigInteger('commission_total')->default(0)->after('commission_vat_amount');

            // ── Host side (settlement statement numbers) ──
            $table->unsignedBigInteger('host_gross_amount')->default(0)->after('commission_total');
            $table->unsignedBigInteger('host_payout_amount')->default(0)->after('host_gross_amount');

            // ── Finance lifecycle stamps ──
            $table->timestamp('guest_invoice_issued_at')->nullable()->after('payout_reference');
            $table->timestamp('host_commission_invoice_issued_at')->nullable()->after('guest_invoice_issued_at');
            $table->timestamp('payout_statement_generated_at')->nullable()->after('host_commission_invoice_issued_at');
            $table->timestamp('financial_completed_at')->nullable()->after('payout_statement_generated_at');
        });

        // Legacy mapping for existing rows: booking_amount → host_gross_amount,
        // commission_amount → commission_amount_ex_vat, vat_amount →
        // guest_vat_amount, total → guest_total. Historical bookings carry NO
        // commission VAT (agreed terms are never rewritten), so their payout
        // stays booking_amount − commission_amount.
        DB::table('bookings')->update([
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
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn([
                'guest_vat_rate', 'guest_vat_amount', 'guest_total',
                'guest_service_fee_amount', 'guest_service_fee_vat_amount',
                'commission_amount_ex_vat', 'commission_vat_rate', 'commission_vat_amount', 'commission_total',
                'host_gross_amount', 'host_payout_amount',
                'guest_invoice_issued_at', 'host_commission_invoice_issued_at',
                'payout_statement_generated_at', 'financial_completed_at',
            ]);
        });
    }
};
