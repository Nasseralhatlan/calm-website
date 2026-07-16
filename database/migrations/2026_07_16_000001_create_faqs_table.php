<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Who the entry is written for — the public page and any client
            // filter by this ('guest' or 'host').
            $table->string('audience', 16)->index();
            // Bilingual Q&A — Arabic required (the app's default language),
            // English optional with fallback, same convention as listings.
            $table->string('question_ar', 500);
            $table->string('question_en', 500)->nullable();
            $table->text('answer_ar');
            $table->text('answer_en')->nullable();
            // Admin-controlled display order within an audience (lower first).
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['audience', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
