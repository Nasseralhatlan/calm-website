<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('group_id')->constrained('attribute_groups')->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('icon')->nullable();
            $table->string('question_ar')->nullable();
            $table->string('question_en')->nullable();
            // Tri-state: 'none' (default), 'optional', or 'required'.
            // Drives whether the host must / can / can't attach photos to the
            // attribute when filling out their place. See App\Enums\AttributePhotoRule.
            $table->string('photo_rule', 16)->default('none');
            $table->string('type', 32);
            $table->json('options')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
