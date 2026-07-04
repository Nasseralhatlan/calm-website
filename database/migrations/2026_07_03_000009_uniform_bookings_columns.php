<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One naming rule, three money items, zero duplication:
 *
 *   Stay        nights, stay_amount                (the stay's value, ex VAT)
 *   Guest       guest_vat_rate/_amount, guest_total (= stay + VAT)
 *   Commission  commission_rate, commission_amount (ex VAT — bases always are),
 *               commission_vat_rate/_amount, commission_total
 *   Host        host_payout_amount                 (= stay − commission_total)
 *
 * Dropped: booking_price (never read), the zero-filled service-fee pair (the
 * feature doesn't exist; returns as a full uniform item when built), and the
 * three per-document stamps duplicated by financial_documents.issued_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->renameColumn('quantity', 'nights');
            $table->renameColumn('host_gross_amount', 'stay_amount');
            $table->renameColumn('commission_amount_ex_vat', 'commission_amount');
            $table->renameColumn('paid_out_at', 'payout_paid_at');
            $table->renameColumn('payment_status_check_attempts', 'payment_check_attempts');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn([
                'booking_price',
                'guest_service_fee_amount',
                'guest_service_fee_vat_amount',
                'guest_invoice_issued_at',
                'host_commission_invoice_issued_at',
                'payout_statement_generated_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->renameColumn('nights', 'quantity');
            $table->renameColumn('stay_amount', 'host_gross_amount');
            $table->renameColumn('commission_amount', 'commission_amount_ex_vat');
            $table->renameColumn('payout_paid_at', 'paid_out_at');
            $table->renameColumn('payment_check_attempts', 'payment_status_check_attempts');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedBigInteger('booking_price')->default(0);
            $table->unsignedBigInteger('guest_service_fee_amount')->default(0);
            $table->unsignedBigInteger('guest_service_fee_vat_amount')->default(0);
            // Approximation good enough for a rollback: the per-document truth
            // lives in financial_documents.issued_at.
            $table->timestamp('guest_invoice_issued_at')->nullable();
            $table->timestamp('host_commission_invoice_issued_at')->nullable();
            $table->timestamp('payout_statement_generated_at')->nullable();
        });
    }
};
