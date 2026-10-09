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
        Schema::create('tax_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique(); // SAP tax code (MWSKZ)
            $table->string('description', 150);
            $table->decimal('rate', 6, 3)->default(0);
            $table->string('tax_procedure', 10)->default('TAXPH');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_codes');
    }
};
