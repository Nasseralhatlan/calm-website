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
            // Day-specific nightly rates. The existing `price` column stays as
            // the fallback/base rate — if a day-specific column is left at 0
            // the host UI can decide to fall back to it.
            $table->unsignedInteger('price_sunday')->default(0)->after('price');
            $table->unsignedInteger('price_monday')->default(0)->after('price_sunday');
            $table->unsignedInteger('price_tuesday')->default(0)->after('price_monday');
            $table->unsignedInteger('price_wednesday')->default(0)->after('price_tuesday');
            $table->unsignedInteger('price_thursday')->default(0)->after('price_wednesday');
            $table->unsignedInteger('price_friday')->default(0)->after('price_thursday');
            $table->unsignedInteger('price_saturday')->default(0)->after('price_friday');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table): void {
            $table->dropColumn([
                'price_sunday',
                'price_monday',
                'price_tuesday',
                'price_wednesday',
                'price_thursday',
                'price_friday',
                'price_saturday',
            ]);
        });
    }
};
