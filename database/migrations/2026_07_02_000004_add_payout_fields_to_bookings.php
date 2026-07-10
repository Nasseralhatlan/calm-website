<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            // Audit trail for manual host payouts: when the admin marked the
            // booking paid, plus the (optional) bank-transfer reference they
            // recorded. payout_status itself already exists ('not_paid'/'paid').
            $table->timestamp('paid_out_at')->nullable()->after('payout_status');
            $table->string('payout_reference', 100)->nullable()->after('paid_out_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['paid_out_at', 'payout_reference']);
        });
    }
};
