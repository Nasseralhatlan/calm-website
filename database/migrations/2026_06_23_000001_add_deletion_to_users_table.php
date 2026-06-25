<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
            // On account deletion the live phone/email are nulled (freeing the
            // unique slot for re-signup); the originals are preserved here so
            // support can find and restore the account.
            if (! Schema::hasColumn('users', 'deleted_phone')) {
                $table->string('deleted_phone', 32)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('users', 'deleted_email')) {
                $table->string('deleted_email')->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (['deleted_at', 'deleted_phone', 'deleted_email'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
