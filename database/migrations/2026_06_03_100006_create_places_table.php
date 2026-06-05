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
            $table->uuid('id')->primary();
            $table->foreignUuid('host_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('place_type_id')->constrained('place_types')->restrictOnDelete();
            // City area is nullable — wizard auto-saves a Draft row after step 1
            // (place type), before the host reaches the area step.
            $table->foreignUuid('city_area_id')->nullable()->constrained('city_areas')->restrictOnDelete();
            // Title is nullable for the same reason — host enters it in step 2.
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            // Base nightly rate plus per-day overrides. A 0 in a per-day column
            // means "use the base" (the host UI shows it as a fallback).
            $table->unsignedInteger('price')->default(0);
            $table->unsignedInteger('price_sunday')->default(0);
            $table->unsignedInteger('price_monday')->default(0);
            $table->unsignedInteger('price_tuesday')->default(0);
            $table->unsignedInteger('price_wednesday')->default(0);
            $table->unsignedInteger('price_thursday')->default(0);
            $table->unsignedInteger('price_friday')->default(0);
            $table->unsignedInteger('price_saturday')->default(0);
            $table->string('check_in_time', 8)->default('15:00');
            $table->string('check_out_time', 8)->default('12:00');
            $table->text('rules')->nullable();
            // Admin's note when rejecting. Shown to the host on the listing +
            // above the wizard when they resume the draft.
            $table->text('rejection_reason')->nullable();
            // When the place was last reviewed (Approve / Reject / Skip).
            $table->timestamp('reviewed_at')->nullable();
            $table->string('status', 16)->default('inactive');
            $table->string('review_status', 24)->default('draft');
            // Wizard step the host last left off on. Used by the Continue link
            // on /my-places to resume the wizard at the right place instead of
            // dumping the host back at step 1.
            $table->unsignedTinyInteger('last_step')->default(1);
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
