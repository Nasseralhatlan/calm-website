<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attribute_groups', function (Blueprint $table): void {
            // Admin-flagged "standalone" sections render as their OWN section
            // in the app instead of inside the general amenities list.
            if (! Schema::hasColumn('attribute_groups', 'is_standalone')) {
                $table->boolean('is_standalone')->default(false)->after('sort_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attribute_groups', function (Blueprint $table): void {
            if (Schema::hasColumn('attribute_groups', 'is_standalone')) {
                $table->dropColumn('is_standalone');
            }
        });
    }
};
