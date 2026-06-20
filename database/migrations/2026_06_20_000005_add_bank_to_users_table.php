<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Free-text bank name (informational) + payout IBAN.
            if (! Schema::hasColumn('users', 'bank')) {
                $table->string('bank')->nullable()->after('country_id');
            }
            // Saudi IBAN: "SA" + 22 digits. Stored normalised, no spaces.
            if (! Schema::hasColumn('users', 'bank_account')) {
                $table->string('bank_account', 34)->nullable()->after('bank');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (['bank', 'bank_account'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
