<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('place_blockings', function (Blueprint $table): void {
            // 'manual' = host-created block; 'ical' = mirrored from an external
            // calendar feed. HostCalendarService and PlaceBlockingResource
            // already read this column defensively.
            $table->string('source', 16)->default('manual')->after('reason');
            $table->foreignUuid('calendar_feed_id')
                ->nullable()
                ->after('source')
                ->constrained('place_calendar_feeds')
                ->cascadeOnDelete();
            // The VEVENT UID from the external feed — the idempotency key for
            // re-syncs (upsert on hit, delete when it vanishes from the feed).
            $table->string('external_uid')->nullable()->after('calendar_feed_id');

            // Manual rows keep both columns null; nullable pairs don't collide.
            $table->unique(['calendar_feed_id', 'external_uid']);
        });
    }

    public function down(): void
    {
        Schema::table('place_blockings', function (Blueprint $table): void {
            $table->dropUnique(['calendar_feed_id', 'external_uid']);
            $table->dropConstrainedForeignId('calendar_feed_id');
            $table->dropColumn(['source', 'external_uid']);
        });
    }
};
