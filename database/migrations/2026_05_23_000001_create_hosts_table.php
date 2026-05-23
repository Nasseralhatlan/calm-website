<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hosts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('phone');
            $table->enum('place_type', ['chalet', 'resthouse', 'camp']);
            $table->timestamps();
        });

        Schema::create('host_facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->unsignedInteger('count')->default(1);
            $table->timestamps();
        });

        Schema::create('host_amenities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->timestamps();
        });

        Schema::create('host_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained()->cascadeOnDelete();
            $table->foreignId('host_facility_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('host_images');
        Schema::dropIfExists('host_amenities');
        Schema::dropIfExists('host_facilities');
        Schema::dropIfExists('hosts');
    }
};
