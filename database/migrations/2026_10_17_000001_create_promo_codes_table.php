<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-created promo codes. The discount is CALM-FUNDED: it lowers what the
 * guest pays, comes out of Calm's commission, and never touches the host's
 * payout. A booking snapshots the code + the discount it got at creation
 * (like every other money field) so later edits to the code never rewrite
 * an existing booking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();          // stored UPPERCASE
            $table->string('discount_type', 20);            // percentage | fixed
            // Percent (e.g. 12.50) for percentage codes, SAR for fixed codes.
            $table->decimal('discount_value', 10, 2);
            // Optional caps/limits — null means "no limit". Money in halalas.
            $table->unsignedBigInteger('max_discount_amount')->nullable();
            $table->unsignedBigInteger('min_stay_amount')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_user')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('note', 500)->nullable();        // internal only
            $table->timestamps();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignUuid('promo_code_id')->nullable()->after('stay_amount')
                ->constrained('promo_codes')->nullOnDelete();
            // Snapshot of the code text — survives the code being deleted.
            $table->string('promo_code', 40)->nullable()->after('promo_code_id');
            // Ex-VAT discount off the stay, Calm-funded. The guest's VAT is
            // charged on (stay − discount); the host's payout ignores it.
            $table->unsignedBigInteger('discount_amount')->default(0)->after('promo_code');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('promo_code_id');
            $table->dropColumn(['promo_code', 'discount_amount']);
        });

        Schema::dropIfExists('promo_codes');
    }
};
