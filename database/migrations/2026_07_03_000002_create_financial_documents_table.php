<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every official invoice, credit note, and settlement statement. Tax
        // documents (guest booking invoice, host commission invoice, credit
        // notes) sync to Qoyod when enabled; the host payout statement is
        // internal only (is_tax_document = false, never sent as an invoice).
        Schema::create('financial_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Business source: 'booking' today (Relation::morphMap), later
            // 'subscription' / 'service_order' with zero schema change.
            $table->string('source_type', 50);
            $table->uuid('source_id');

            $table->string('document_type', 50);      // invoice | credit_note | debit_note | settlement_statement
            $table->string('document_subtype', 100);  // guest_booking_invoice | host_commission_invoice | host_payout_statement | guest_booking_credit_note | host_commission_credit_note

            $table->string('seller_type', 50)->nullable(); // calm | host | vendor
            $table->uuid('seller_id')->nullable();
            $table->string('buyer_type', 50)->nullable();  // guest | host | calm
            $table->uuid('buyer_id')->nullable();

            $table->string('direction', 50);                // sales | internal
            $table->string('status', 50)->default('draft'); // draft | pending_provider | issued | failed | credited | canceled
            $table->boolean('is_tax_document')->default(false);

            $table->string('currency', 3)->default('SAR');
            $table->unsignedBigInteger('subtotal_amount')->default(0);
            $table->unsignedBigInteger('vat_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);

            // External accounting provider (Qoyod) references.
            $table->string('external_provider', 50)->nullable();
            $table->string('external_contact_id', 100)->nullable();
            $table->string('external_document_id', 100)->nullable();
            $table->string('external_document_number', 100)->nullable();
            $table->string('external_uuid', 150)->nullable();
            $table->text('external_pdf_url')->nullable();
            $table->text('external_xml_url')->nullable();
            $table->text('external_qr')->nullable();
            $table->string('external_status', 100)->nullable();
            $table->json('external_payload')->nullable();
            $table->json('external_response')->nullable();

            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            // One document of each subtype per source — the idempotency anchor
            // that makes double-issuing impossible.
            $table->unique(['source_type', 'source_id', 'document_subtype'], 'financial_documents_unique_source_subtype');
            $table->index(['source_type', 'source_id']);
            $table->index(['buyer_type', 'buyer_id']);
            $table->index(['status', 'document_subtype']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_documents');
    }
};
