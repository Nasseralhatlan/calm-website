<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // External iCal URLs (Airbnb / Gathern / Google) the host pasted into a
        // place. A scheduled job fetches each feed and mirrors its events into
        // place_blockings (source = 'ical').
        Schema::create('place_calendar_feeds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('place_id')->constrained('places')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('url', 2048);
            $table->timestamp('last_synced_at')->nullable();
            // 'ok' | 'error' — last fetch outcome, shown on the sync UI.
            $table->string('last_status', 16)->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_calendar_feeds');
    }
};
