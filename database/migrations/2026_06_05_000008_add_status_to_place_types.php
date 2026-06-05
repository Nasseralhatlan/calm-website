<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('place_types', function (Blueprint $table): void {
            // Same lifecycle flag the geo tables use. Only active types appear
            // in the host wizard's "What kind of place?" picker, so we can
            // pre-seed types we're not ready to launch (apartments, villas,
            // camps, etc.) without surfacing them.
            $table->string('status', 16)->default('active')->index()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('place_types', function (Blueprint $table): void {
            $table->dropColumn('status');
        });
    }
};
