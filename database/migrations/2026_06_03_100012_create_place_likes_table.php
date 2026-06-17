<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_likes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('place_id')->constrained('places')->cascadeOnDelete();
            $table->timestamps();

            // One like per (user, place); the unique acts as upsert anchor too.
            $table->unique(['user_id', 'place_id']);
            // Sort-by-recent + count(*) lookups for the "most liked" endpoint.
            $table->index(['place_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_likes');
    }
};
