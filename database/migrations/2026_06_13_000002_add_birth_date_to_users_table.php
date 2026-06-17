<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded: some environments already have this column from an earlier
        // inline edit to the create-users migration; only add where missing.
        if (Schema::hasColumn('users', 'birth_date')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->date('birth_date')->nullable()->after('age');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'birth_date')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('birth_date');
        });
    }
};
