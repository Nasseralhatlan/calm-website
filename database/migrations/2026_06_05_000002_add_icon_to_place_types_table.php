<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('place_types', function (Blueprint $table): void {
            // Emoji or short identifier rendered next to the place type name
            // in the host's place-creation flow and the admin lists.
            $table->string('icon', 32)->nullable()->after('name_en');
        });
    }

    public function down(): void
    {
        Schema::table('place_types', function (Blueprint $table): void {
            $table->dropColumn('icon');
        });
    }
};
