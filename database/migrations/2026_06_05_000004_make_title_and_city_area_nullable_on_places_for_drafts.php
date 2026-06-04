<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table): void {
            // Drafts are created the moment a host picks a place type — the
            // title and area only land in step 2 / step 3 of the wizard.
            $table->string('title')->nullable()->change();
            $table->foreignId('city_area_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table): void {
            $table->string('title')->nullable(false)->change();
            $table->foreignId('city_area_id')->nullable(false)->change();
        });
    }
};
