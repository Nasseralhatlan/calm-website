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
        // inline edit to the create-places migration; only add where missing.
        if (Schema::hasColumn('places', 'max_guests')) {
            return;
        }

        Schema::table('places', function (Blueprint $table): void {
            // Sleeps how many — nullable so wizard drafts can save before the
            // host has set capacity. Final submit enforces it via
            // StorePlaceRequest (range 1..50).
            $table->unsignedTinyInteger('max_guests')->nullable()->after('check_out_time');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('places', 'max_guests')) {
            return;
        }

        Schema::table('places', function (Blueprint $table): void {
            $table->dropColumn('max_guests');
        });
    }
};
