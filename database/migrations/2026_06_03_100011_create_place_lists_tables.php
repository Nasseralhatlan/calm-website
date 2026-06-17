<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-curated collections of places (e.g. "Featured chalets",
        // "Editor's picks"). Each list becomes a section on the landing page,
        // ordered by `sort_order` (lower = higher on the page).
        Schema::create('place_lists', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('description_ar')->nullable();
            $table->string('description_en')->nullable();
            // Emoji rendered next to the list title on the landing page.
            $table->string('icon', 32)->nullable();
            // Position the list takes on the landing page — lower number = higher up.
            $table->unsignedInteger('sort_order')->default(0);
            // Lifecycle flag, same vocabulary as countries / cities / place_types.
            // Only Active lists surface on the landing page.
            $table->string('status', 16)->default('active')->index();
            $table->timestamps();

            $table->index('sort_order');
        });

        // Pivot — many-to-many between place_lists and places, with the place
        // order within the list (lower = first card in the section row).
        Schema::create('place_list_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('place_list_id')->constrained('place_lists')->cascadeOnDelete();
            $table->foreignUuid('place_id')->constrained('places')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['place_list_id', 'place_id']);
            $table->index(['place_list_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_list_items');
        Schema::dropIfExists('place_lists');
    }
};
