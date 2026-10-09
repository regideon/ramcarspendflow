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
        Schema::create('rfp_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfp_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->foreignId('rfp_subcategory_id')->constrained();
            $table->foreignId('gl_account_id')->constrained(); // copied from the subcategory when saved
            $table->foreignId('cost_center_id')->nullable()->constrained();
            $table->string('invoice_number', 50)->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('po_number', 15)->nullable();
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2); // in the RFP currency
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfp_items');
    }
};
