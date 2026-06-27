<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table): void {
            // Per-language listing content. The existing title/description/rules
            // columns stay as the canonical value (= *_ar ?: *_en), so search,
            // snapshots and non-localized screens keep working.
            if (! Schema::hasColumn('places', 'title_ar')) {
                $table->string('title_ar')->nullable()->after('title');
            }
            if (! Schema::hasColumn('places', 'title_en')) {
                $table->string('title_en')->nullable()->after('title_ar');
            }
            if (! Schema::hasColumn('places', 'description_ar')) {
                $table->text('description_ar')->nullable()->after('description');
            }
            if (! Schema::hasColumn('places', 'description_en')) {
                $table->text('description_en')->nullable()->after('description_ar');
            }
            if (! Schema::hasColumn('places', 'rules_ar')) {
                $table->text('rules_ar')->nullable()->after('rules');
            }
            if (! Schema::hasColumn('places', 'rules_en')) {
                $table->text('rules_en')->nullable()->after('rules_ar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table): void {
            foreach (['title_ar', 'title_en', 'description_ar', 'description_en', 'rules_ar', 'rules_en'] as $column) {
                if (Schema::hasColumn('places', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
