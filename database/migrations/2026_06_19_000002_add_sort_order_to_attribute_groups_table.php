<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('attribute_groups', 'sort_order')) {
            return;
        }

        Schema::table('attribute_groups', function (Blueprint $table): void {
            // Admin-controlled order of attribute groups (drag-and-drop sort
            // page). Defaults to 0 → existing groups fall back to name_en.
            $table->unsignedInteger('sort_order')->default(0)->after('name_en');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('attribute_groups', 'sort_order')) {
            return;
        }

        Schema::table('attribute_groups', function (Blueprint $table): void {
            $table->dropColumn('sort_order');
        });
    }
};
