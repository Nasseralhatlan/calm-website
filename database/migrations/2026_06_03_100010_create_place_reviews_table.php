<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            // booking_id will be wired up once the bookings table lands; nullable for now.
            $table->unsignedBigInteger('booking_id')->nullable()->index();
            $table->unsignedTinyInteger('rate');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['place_id', 'rate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_reviews');
    }
};
