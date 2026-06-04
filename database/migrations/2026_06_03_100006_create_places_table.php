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
            $table->id();
            $table->foreignId('host_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('place_type_id')->constrained('place_types')->restrictOnDelete();
            $table->foreignId('city_area_id')->constrained('city_areas')->restrictOnDelete();
            $table->string('title');
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
