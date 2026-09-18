<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Occasion planning leads — a guest describes what they want to celebrate and
 * the events team calls them back.
 *
 * Deliberately one flat table: the occasion type gets its own column because
 * that's what we slice demand by, and everything else the wizard collects
 * (needs, guest count, venue, notes) lands in `details` as-is. The wizard's
 * questions will keep changing while we test this — a json blob absorbs that
 * without a migration per tweak. No contact columns: the lead is tied to the
 * account and we call the account's phone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occasion_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            // What they're celebrating, e.g. birthday | wedding | other.
            $table->string('occasion_type', 64);
            // Everything else the wizard asked, e.g.
            // {"guests": 50, "needs": ["coffee"], "venue_status": "help", "notes": "..."}.
            $table->json('details')->nullable();

            // new | under_processing | ongoing | completed
            $table->string('status', 32)->default('new');
            $table->timestamps();

            // The guest's own list ("follow your request"), newest first.
            $table->index(['user_id', 'created_at']);
            // The events team's queue — filter by status, oldest first.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occasion_requests');
    }
};
