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
            // Qoyod contact id for GUESTS (hosts keep theirs on their tax
            // profile). Set on first invoice sync, reused forever after.
            $table->string('qoyod_customer_id', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('qoyod_customer_id');
        });
    }
};
