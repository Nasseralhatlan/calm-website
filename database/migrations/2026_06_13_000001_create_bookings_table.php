<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('place_id')->constrained('places')->cascadeOnDelete();
            $table->foreignUuid('guest_user_id')->constrained('users')->cascadeOnDelete();
            // Denormalised from the place at booking time so payout/reporting
            // queries don't need to join back through the place.
            $table->foreignUuid('host_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('booking_status')->default('pending_payment');

            // Inclusive stay window + the place's check-in/out times and rules,
            // snapshotted so a later host edit never rewrites a past agreement.
            $table->date('start_date');
            $table->date('end_date');
            $table->string('check_in_time')->nullable();
            $table->string('check_out_time')->nullable();
            $table->text('rules')->nullable();
            $table->unsignedInteger('guests');

            // Price snapshot. All money is in halalas (minor units) so the maths
            // stays exact and the gateway amount is a plain integer.
            $table->unsignedBigInteger('booking_price');   // per-day base rate snapshot
            $table->unsignedInteger('quantity');           // number of days
            $table->unsignedBigInteger('booking_amount');  // sum of per-day prices

            // Calm's commission — taken from the host's payout, NOT charged to
            // the guest. Tracked here for payout reconciliation.
            $table->decimal('commission_rate', 5, 2);
            $table->unsignedBigInteger('commission_amount');

            // VAT on the booking amount — part of what the guest pays.
            $table->decimal('vat_rate', 5, 2);
            $table->unsignedBigInteger('vat_amount');

            // What the guest is charged: booking_amount + vat_amount.
            $table->unsignedBigInteger('total');

            // Moyasar hosted-invoice handles.
            $table->string('payment_id')->nullable();
            $table->string('payment_url')->nullable();      // hosted checkout url
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->nullable();
            $table->unsignedInteger('payment_status_check_attempts')->default(0);
            $table->json('payment_response')->nullable();

            // Host settlement: paid out yet or not.
            $table->string('payout_status')->default('not_paid');

            $table->timestamp('expires_at')->nullable();   // date-hold deadline
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            // Availability lookups: active bookings overlapping a window.
            $table->index(['place_id', 'booking_status', 'start_date', 'end_date']);
            // The expiry sweep: pending holds past their deadline.
            $table->index(['booking_status', 'expires_at']);
            $table->index('payment_id');
            $table->index(['host_user_id', 'payout_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
