<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('places', 'location_url')) {
            Schema::table('places', function (Blueprint $table): void {
                // A map link (Google Maps, etc.) the host pastes. Sensitive —
                // only surfaced to a guest once their booking is confirmed.
                $table->string('location_url', 2048)->nullable()->after('rules');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('places', 'location_url')) {
            Schema::table('places', function (Blueprint $table): void {
                $table->dropColumn('location_url');
            });
        }
    }
};
