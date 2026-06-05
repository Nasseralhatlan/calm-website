<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('host_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('place_type_id')->constrained('place_types')->restrictOnDelete();
            $table->foreignUuid('city_area_id')->nullable()->constrained('city_areas')->restrictOnDelete();
            // Title is nullable — the wizard creates a Draft row after step 1
            // (place type chosen) and the host enters the title in step 2.
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('price')->default(0);
            $table->string('check_in_time', 8)->default('15:00');
            $table->string('check_out_time', 8)->default('12:00');
            $table->text('rules')->nullable();
            $table->string('status', 16)->default('inactive');
            $table->string('review_status', 24)->default('draft');
            $table->timestamps();

            $table->index(['status', 'review_status']);
            $table->index('host_user_id');
            $table->index('place_type_id');
            $table->index('city_area_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};
