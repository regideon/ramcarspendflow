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
        Schema::create('withholding_tax_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('withholding_tax_type_id')->nullable()->constrained();
            $table->string('code', 2)->unique(); // SAP withholding tax code (WT_WITHCD)
            $table->string('atc', 10)->nullable(); // BIR alphanumeric tax code, e.g. WC010
            $table->string('description', 150);
            $table->decimal('rate', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withholding_tax_codes');
    }
};
