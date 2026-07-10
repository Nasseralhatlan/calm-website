<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hosts must exist as Qoyod customers (Calm invoices them for the
        // platform commission). Individuals get a minimal auto-created profile
        // at first finance finalization; company details are filled by admin.
        Schema::create('host_tax_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('host_user_id')->unique()->constrained('users')->cascadeOnDelete();

            $table->string('host_type', 30)->default('individual'); // individual | company
            $table->string('legal_name');
            $table->string('commercial_registration_number', 100)->nullable();
            $table->boolean('vat_registered')->default(false);
            $table->string('vat_number', 50)->nullable();
            $table->date('vat_registration_date')->nullable();

            $table->string('qoyod_customer_id', 100)->nullable();
            $table->string('qoyod_vendor_id', 100)->nullable(); // future supplier bills

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('host_tax_profiles');
    }
};
