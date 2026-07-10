<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_document_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('financial_document_id')
                ->constrained('financial_documents')
                ->cascadeOnDelete();

            $table->string('description', 500);
            $table->decimal('quantity', 12, 2)->default(1);
            $table->unsignedBigInteger('unit_amount')->default(0);
            $table->unsignedBigInteger('subtotal_amount')->default(0);
            $table->decimal('vat_rate', 8, 2)->default(0);
            $table->unsignedBigInteger('vat_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);

            // Optional pointer to what the line represents (booking, fee, …).
            $table->string('source_type', 50)->nullable();
            $table->uuid('source_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_document_lines');
    }
};
