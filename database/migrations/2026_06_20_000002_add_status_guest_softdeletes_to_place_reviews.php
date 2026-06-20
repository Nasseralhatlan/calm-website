<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First run? (used to gate index creation + the legacy backfill).
        $fresh = ! Schema::hasColumn('place_reviews', 'status');

        Schema::table('place_reviews', function (Blueprint $table) use ($fresh): void {
            if (! Schema::hasColumn('place_reviews', 'guest_user_id')) {
                $table->foreignUuid('guest_user_id')->nullable()->after('place_id')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('place_reviews', 'status')) {
                $table->string('status', 16)->default('under_review')->after('comment');
            }
            if (! Schema::hasColumn('place_reviews', 'deleted_at')) {
                $table->softDeletes();
            }

            if ($fresh) {
                // One active review per (place, guest); + the public published list.
                $table->index(['place_id', 'guest_user_id']);
                $table->index(['place_id', 'status']);
            }
        });

        // Every review that existed before moderation shipped is treated as live.
        // (A new column with a default fills existing rows with that default, so
        // we re-stamp them all to "published" on this first run.)
        if ($fresh) {
            DB::table('place_reviews')->update(['status' => 'published']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('place_reviews', 'status')) {
            return;
        }

        Schema::table('place_reviews', function (Blueprint $table): void {
            $table->dropIndex(['place_id', 'guest_user_id']);
            $table->dropIndex(['place_id', 'status']);
            $table->dropConstrainedForeignId('guest_user_id');
            $table->dropColumn(['status', 'deleted_at']);
        });
    }
};
