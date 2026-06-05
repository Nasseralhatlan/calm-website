<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('country_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            // Emoji or icon — rendered in the host wizard's city picker.
            $table->string('avatar')->nullable();
            // Lifecycle flag — only Active cities surface in pickers. See
            // App\Enums\GeoStatus.
            $table->string('status', 16)->default('active')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
