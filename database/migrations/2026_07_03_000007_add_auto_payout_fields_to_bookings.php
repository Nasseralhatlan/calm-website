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
            // Moyasar Payouts execution trail. payout_status (existing string
            // column) gains the value 'processing' while a transfer is in
            // flight at Moyasar/the bank.
            $table->string('payout_id', 100)->nullable()->after('payout_reference');
            $table->string('payout_failure', 500)->nullable()->after('payout_id');
            // Confirmed-failure counter feeding the Moyasar sequence_number —
            // each bank-level failure consumes a sequence, so the retry needs
            // the next one. Never incremented on ambiguous (timeout) errors.
            $table->unsignedSmallInteger('payout_attempts')->default(0)->after('payout_failure');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['payout_id', 'payout_failure', 'payout_attempts']);
        });
    }
};
