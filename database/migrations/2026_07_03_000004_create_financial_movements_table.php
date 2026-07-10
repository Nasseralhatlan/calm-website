<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Money MOVEMENT records (not invoices): who paid whom, how, with what
        // external reference. Balances are always derived from these rows.
        Schema::create('financial_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->string('source_type', 50);
            $table->uuid('source_id');

            // guest_payment | commission_withheld | host_payout_payable |
            // host_payout | guest_refund | payment_provider_fee
            $table->string('movement_type', 100);

            $table->string('from_party_type', 50)->nullable(); // guest | host | calm
            $table->uuid('from_party_id')->nullable();
            $table->string('to_party_type', 50)->nullable();
            $table->uuid('to_party_id')->nullable();

            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('SAR');

            $table->string('provider', 50)->nullable(); // moyasar | bank | manual | qoyod
            $table->string('provider_transaction_id', 150)->nullable();
            $table->string('provider_reference', 150)->nullable();

            $table->string('status', 50)->default('pending'); // pending | succeeded | failed | reversed
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['movement_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_movements');
    }
};
