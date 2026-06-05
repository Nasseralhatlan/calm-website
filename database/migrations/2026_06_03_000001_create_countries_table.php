<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('country_code', 8)->unique();
            // International dialing prefix (e.g. "+966") — stored with the
            // leading plus so views can render it as-is and the SMS adapter
            // can strip it when building the wire-format number.
            $table->string('dial_code', 8)->nullable();
            $table->string('name_ar');
            $table->string('name_en');
            // Flag emoji / icon — rendered next to the country name in admin
            // tables and the login country-code dropdown.
            $table->string('avatar')->nullable();
            // Lifecycle flag — only Active countries surface in the login
            // picker and host wizard. See App\Enums\GeoStatus.
            $table->string('status', 16)->default('active')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
