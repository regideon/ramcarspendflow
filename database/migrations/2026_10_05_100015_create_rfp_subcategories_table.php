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
        Schema::create('rfp_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfp_category_id')->constrained();
            $table->foreignId('gl_account_id')->constrained();
            $table->string('name', 150);
            $table->string('document_type', 2)->default('KR'); // SAP document type (BLART)
            $table->string('posting_key', 2)->default('40'); // SAP posting key (BSCHL)
            $table->boolean('requires_cost_center')->default(false);
            $table->boolean('requires_po_number')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['rfp_category_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfp_subcategories');
    }
};
