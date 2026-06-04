<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hosts pick attributes from the global pool when filling out a place;
        // there's no need for an admin-curated per-place-type assignment.
        Schema::dropIfExists('place_type_attributes');
    }

    public function down(): void
    {
        Schema::create('place_type_attributes', function ($table): void {
            $table->id();
            $table->foreignId('place_type_id')->constrained('place_types')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->string('requirement_level', 16);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['place_type_id', 'attribute_id']);
        });
    }
};
