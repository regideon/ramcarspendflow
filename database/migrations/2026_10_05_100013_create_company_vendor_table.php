<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_vendor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('reconciliation_account', 10); // AKONT
            $table->string('payment_terms', 4)->nullable(); // ZTERM, e.g. V30N
            $table->string('payment_methods', 10)->nullable(); // ZWELS: allowed method codes, e.g. CT
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'vendor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_vendor');
    }
};
