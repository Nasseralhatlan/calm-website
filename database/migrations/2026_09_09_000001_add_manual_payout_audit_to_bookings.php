<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for admin-initiated payouts. A payout can now be settled by an
 * admin before the normal gates (stay completed + invoices issued + hold
 * window) — or handed over in cash — so the row has to say HOW it was paid,
 * WHO recorded it and WHY, not just that it is `paid`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'payout_method')) {
                // moyasar (automatic) | bank (manual transfer) | cash
                $table->string('payout_method', 20)->nullable()->after('payout_reference');
            }
            if (! Schema::hasColumn('bookings', 'payout_note')) {
                // Why it was settled by hand / released early.
                $table->string('payout_note', 500)->nullable()->after('payout_method');
            }
            if (! Schema::hasColumn('bookings', 'payout_settled_by')) {
                $table->foreignUuid('payout_settled_by')->nullable()->after('payout_note')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('bookings', 'payout_forced_at')) {
                // Set when an admin released the money before it was payable.
                $table->timestamp('payout_forced_at')->nullable()->after('payout_settled_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'payout_settled_by')) {
                $table->dropConstrainedForeignId('payout_settled_by');
            }
            $table->dropColumn(array_values(array_filter(
                ['payout_method', 'payout_note', 'payout_forced_at'],
                fn (string $c): bool => Schema::hasColumn('bookings', $c),
            )));
        });
    }
};
