<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hosts', function (Blueprint $table) {
            $table->string('title', 120)->nullable()->after('place_type');
            $table->text('description')->nullable()->after('title');
            $table->unsignedSmallInteger('max_guests')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('hosts', function (Blueprint $table) {
            $table->dropColumn(['title', 'description', 'max_guests']);
        });
    }
};
