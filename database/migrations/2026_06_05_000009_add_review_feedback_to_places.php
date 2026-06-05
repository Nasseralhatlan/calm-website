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
            // Admin's note when rejecting a submission. Shown to the host on
            // the My Places listing + above the wizard when they resume the
            // draft, so they know what to fix before resubmitting.
            $table->text('rejection_reason')->nullable()->after('rules');
            // When the place was last reviewed — useful for timeline + sorting
            // pending older items first.
            $table->timestamp('reviewed_at')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table): void {
            $table->dropColumn(['rejection_reason', 'reviewed_at']);
        });
    }
};
