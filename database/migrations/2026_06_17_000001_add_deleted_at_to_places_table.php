<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-delete support for places. Deleting a place from the admin/host tabs
 * archives it (hidden everywhere, reversible) rather than hard-deleting — so
 * its bookings, reviews and history stay intact. Guarded so it's safe to run
 * on every environment.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('places', 'deleted_at')) {
            Schema::table('places', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('places', 'deleted_at')) {
            Schema::table('places', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }
};
