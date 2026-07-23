<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Identical, interchangeable units of one listing ("وحدة ١", "وحدة ٢"…).
        // Guests never see or pick one — capacity = row count, and each booking
        // is auto-assigned a free unit so the host knows which one it landed in.
        // A place with NO rows is a classic single-unit place.
        Schema::create('place_units', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('place_id')->constrained('places')->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['place_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_units');
    }
};
