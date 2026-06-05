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
            // The wizard step the host was on when they last left the form.
            // Used to resume the wizard at the right step from the "Continue"
            // link on /my-places instead of dumping them back at step 1.
            $table->unsignedTinyInteger('last_step')->default(1)->after('review_status');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table): void {
            $table->dropColumn('last_step');
        });
    }
};
