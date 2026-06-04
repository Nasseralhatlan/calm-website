<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table): void {
            // 3-state replacement for the old photo_required boolean:
            // 'none' (default), 'optional', or 'required'.
            $table->string('photo_rule', 16)->default('none')->after('icon');
        });

        Schema::table('attributes', function (Blueprint $table): void {
            $table->dropColumn('photo_required');
        });
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table): void {
            $table->boolean('photo_required')->default(false)->after('icon');
        });

        Schema::table('attributes', function (Blueprint $table): void {
            $table->dropColumn('photo_rule');
        });
    }
};
