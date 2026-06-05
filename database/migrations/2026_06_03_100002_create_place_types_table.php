<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_types', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name_ar');
            $table->string('name_en');
            // Emoji rendered next to the place-type name in the host wizard's
            // "What kind of place is it?" picker and the admin lists.
            $table->string('icon', 32)->nullable();
            // Lifecycle flag — only Active types appear in the host wizard.
            // Lets us pre-seed types we're not ready to launch yet.
            $table->string('status', 16)->default('active')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_types');
    }
};
