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
            // The unit this booking occupies (auto-assigned at creation for
            // multi-unit places; null for classic single-unit listings).
            // nullOnDelete: removing a unit keeps its bookings, label-less.
            if (! Schema::hasColumn('bookings', 'place_unit_id')) {
                $table->foreignUuid('place_unit_id')->nullable()->after('place_id')
                    ->constrained('place_units')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'place_unit_id')) {
                $table->dropConstrainedForeignId('place_unit_id');
            }
        });
    }
};
