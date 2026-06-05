<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

/*
 * Historical: this migration previously made `places.title` and
 * `places.city_area_id` nullable to support wizard draft rows that are
 * created the moment the host picks a place type (before the title or
 * area are entered). With the UUID PK refactor we folded those nullable
 * declarations into the original create_places migration, so this file
 * is now a deliberate no-op kept for migration-history continuity.
 */
return new class extends Migration
{
    public function up(): void
    {
        // no-op — handled by create_places migration directly.
    }

    public function down(): void
    {
        // no-op.
    }
};
