<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('place_reviews', function (Blueprint $table): void {
            // Display name for reviews imported for unregistered guests
            // (no user row at all). Organic reviews keep guest_user_id and
            // leave this null.
            if (! Schema::hasColumn('place_reviews', 'reviewer_name')) {
                $table->string('reviewer_name')->nullable()->after('guest_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('place_reviews', function (Blueprint $table): void {
            if (Schema::hasColumn('place_reviews', 'reviewer_name')) {
                $table->dropColumn('reviewer_name');
            }
        });
    }
};
