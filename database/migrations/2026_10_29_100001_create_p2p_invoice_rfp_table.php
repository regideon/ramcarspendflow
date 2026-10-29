<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RFPs (usually supplier down payments) the AP Processor links to a P2P invoice of the
 * same vendor, for reference only: linking changes no amounts or entries.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('p2p_invoice_rfp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('p2p_invoice_id')->constrained('p2p_invoices')->cascadeOnDelete();
            $table->foreignId('rfp_id')->constrained('rfps')->cascadeOnDelete();
            $table->foreignId('linked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['p2p_invoice_id', 'rfp_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('p2p_invoice_rfp');
    }
};
