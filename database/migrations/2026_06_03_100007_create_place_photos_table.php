<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->foreignId('place_attribute_id')->nullable()->constrained('attributes')->nullOnDelete();
            $table->string('path');
            $table->boolean('is_cover')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['place_id', 'sort_order']);
            $table->index(['place_id', 'is_cover']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_photos');
    }
};
