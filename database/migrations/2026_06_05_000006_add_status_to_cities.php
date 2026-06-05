<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table): void {
            // Same lifecycle flag the countries table has — lets us seed
            // cities we'll launch in later without surfacing them yet.
            $table->string('status', 16)->default('active')->index()->after('name_en');
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table): void {
            $table->dropColumn('status');
        });
    }
};
