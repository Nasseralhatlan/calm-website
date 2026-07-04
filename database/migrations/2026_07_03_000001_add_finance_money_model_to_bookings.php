<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The finance money model, as ONE delta against the production schema.
 *
 * One naming rule — components end `_amount` (always ex VAT), totals `_total`,
 * rates `_rate` — across three items derived from a single stay value:
 *
 *   Stay        nights, stay_amount
 *   Guest       guest_vat_rate/_amount, guest_total   (= stay + VAT)
 *   Commission  commission_rate, commission_amount,
 *               commission_vat_rate/_amount, commission_total (VAT ON TOP)
 *   Host        host_payout_amount                    (= stay − commission_total)
 *
 * The existing columns already held these numbers under older names, so this
 * is mostly renames. Commission VAT is NEW: existing rows keep 0 so their
 * agreed payouts never change; new bookings snapshot the configured rate.
 * Also adds the automatic-payout (Moyasar) trail and the documents-done gate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->renameColumn('booking_amount', 'stay_amount');
            $table->renameColumn('vat_rate', 'guest_vat_rate');
            $table->renameColumn('vat_amount', 'guest_vat_amount');
            $table->renameColumn('total', 'guest_total');
            $table->renameColumn('quantity', 'nights');
            $table->renameColumn('paid_out_at', 'payout_paid_at');
            $table->renameColumn('payment_status_check_attempts', 'payment_check_attempts');
            // commission_amount keeps its name — it was always the ex-VAT figure.
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->decimal('commission_vat_rate', 5, 2)->default(0)->after('commission_amount');
            $table->unsignedBigInteger('commission_vat_amount')->default(0)->after('commission_vat_rate');
            $table->unsignedBigInteger('commission_total')->default(0)->after('commission_vat_amount');
            $table->unsignedBigInteger('host_payout_amount')->default(0)->after('commission_total');

            // Set once all financial documents are issued — the
            // documents-before-money gate for payouts.
            $table->timestamp('financial_completed_at')->nullable()->after('payout_reference');

            // Moyasar Payouts execution trail. payout_status gains the value
            // 'processing' while a transfer is in flight (string col, no DDL).
            $table->string('payout_id', 100)->nullable()->after('financial_completed_at');
            $table->string('payout_failure', 500)->nullable()->after('payout_id');
            // Confirmed-failure counter feeding the Moyasar sequence_number —
            // each bank-level failure consumes a sequence, so the retry needs
            // the next one. Never incremented on ambiguous (timeout) errors.
            $table->unsignedSmallInteger('payout_attempts')->default(0)->after('payout_failure');

            // Replaced by stay_amount ÷ nights where needed; was never read.
            $table->dropColumn('booking_price');
        });

        // Existing rows: commission VAT stays 0 (their payout terms are
        // frozen), so total = amount and payout = stay − commission.
        DB::table('bookings')
            ->where('commission_total', 0)
            ->update([
                'commission_total' => DB::raw('commission_amount'),
                'host_payout_amount' => DB::raw('stay_amount - commission_amount'),
            ]);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedBigInteger('booking_price')->default(0);
            $table->dropColumn([
                'commission_vat_rate', 'commission_vat_amount', 'commission_total',
                'host_payout_amount', 'financial_completed_at',
                'payout_id', 'payout_failure', 'payout_attempts',
            ]);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->renameColumn('stay_amount', 'booking_amount');
            $table->renameColumn('guest_vat_rate', 'vat_rate');
            $table->renameColumn('guest_vat_amount', 'vat_amount');
            $table->renameColumn('guest_total', 'total');
            $table->renameColumn('nights', 'quantity');
            $table->renameColumn('payout_paid_at', 'paid_out_at');
            $table->renameColumn('payment_check_attempts', 'payment_status_check_attempts');
        });
    }
};
