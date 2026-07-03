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
            // Secret for the public iCal export URL (/ical/places/{id}/{token}.ics).
            // Nullable — minted lazily the first time the host opens calendar
            // sync, so places that never sync never carry a token.
            $table->string('calendar_token', 64)->nullable()->unique()->after('last_step');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table): void {
            $table->dropColumn('calendar_token');
        });
    }
};
