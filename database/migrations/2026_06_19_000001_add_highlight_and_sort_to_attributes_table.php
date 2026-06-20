<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table): void {
            // Flag the most important amenities so they render in a separate,
            // prominent "Highlights" section (additively — they stay in their
            // group too).
            if (! Schema::hasColumn('attributes', 'is_highlighted')) {
                $table->boolean('is_highlighted')->default(false)->after('type');
            }

            // Admin-controlled display order for amenities, honored in the
            // add-place wizard and on the place page. Defaults to 0 → existing
            // rows fall back to name_en ordering (no surprise reordering).
            if (! Schema::hasColumn('attributes', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_highlighted');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table): void {
            foreach (['is_highlighted', 'sort_order'] as $column) {
                if (Schema::hasColumn('attributes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
