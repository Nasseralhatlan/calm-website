<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('place_attributes', function (Blueprint $table): void {
            // Extra free-form text beside the value (e.g. "heated, 4m deep" next to a "pool" select value).
            $table->text('description')->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('place_attributes', function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }
};
