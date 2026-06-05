<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_type_attributes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('place_type_id')->constrained('place_types')->cascadeOnDelete();
            $table->foreignUuid('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->string('requirement_level', 16);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['place_type_id', 'attribute_id']);
            $table->index(['place_type_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_type_attributes');
    }
};
