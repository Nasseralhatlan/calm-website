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
            // The name on the bank account (may differ from the profile name —
            // Arabic vs English spelling). Payout destinations prefer it over
            // users.name so transfers match the bank's own record.
            if (! Schema::hasColumn('users', 'bank_account_name')) {
                $table->string('bank_account_name', 120)->nullable()->after('bank_account');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'bank_account_name')) {
                $table->dropColumn('bank_account_name');
            }
        });
    }
};
