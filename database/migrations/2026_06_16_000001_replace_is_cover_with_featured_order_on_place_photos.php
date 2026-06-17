<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the single `is_cover` flag with `featured_order`: a nullable position
 * (0..9) in the place's "shown outside" showcase. null = not shown outside;
 * the lowest (0) is the cover. Guarded so it's safe to run on any environment.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('place_photos', 'featured_order')) {
            Schema::table('place_photos', function (Blueprint $table): void {
                $table->unsignedInteger('featured_order')->nullable()->after('sort_order');
                $table->index(['place_id', 'featured_order']);
            });
        }

        // Carry the existing cover over as the first featured photo.
        if (Schema::hasColumn('place_photos', 'is_cover')) {
            DB::table('place_photos')->where('is_cover', true)->update(['featured_order' => 0]);

            Schema::table('place_photos', function (Blueprint $table): void {
                $table->dropIndex(['place_id', 'is_cover']);
                $table->dropColumn('is_cover');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('place_photos', 'is_cover')) {
            Schema::table('place_photos', function (Blueprint $table): void {
                $table->boolean('is_cover')->default(false)->after('path');
                $table->index(['place_id', 'is_cover']);
            });
            DB::table('place_photos')->where('featured_order', 0)->update(['is_cover' => true]);
        }

        if (Schema::hasColumn('place_photos', 'featured_order')) {
            Schema::table('place_photos', function (Blueprint $table): void {
                $table->dropIndex(['place_id', 'featured_order']);
                $table->dropColumn('featured_order');
            });
        }
    }
};
