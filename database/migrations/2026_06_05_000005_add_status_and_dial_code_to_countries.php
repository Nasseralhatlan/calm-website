<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table): void {
            // Lifecycle flag — only active countries are surfaced in the login
            // picker and in the host wizard's geo dropdowns.
            $table->string('status', 16)->default('active')->index()->after('name_en');
            // International dialing prefix (e.g. "+966"). Stored with the leading
            // plus so views can render it as-is and the SMS adapter can derive
            // the numeric prefix when needed.
            $table->string('dial_code', 8)->nullable()->after('country_code');
        });
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table): void {
            $table->dropColumn(['status', 'dial_code']);
        });
    }
};
