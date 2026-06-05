<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table): void {
            // Flag emoji / icon — rendered next to the country name in the
            // admin tables and in the login country-code dropdown. Matches
            // the `avatar` shape on `cities` for consistency.
            $table->string('avatar')->nullable()->after('name_en');
        });
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table): void {
            $table->dropColumn('avatar');
        });
    }
};
