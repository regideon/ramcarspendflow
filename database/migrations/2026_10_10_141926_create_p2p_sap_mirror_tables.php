<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 1 — SAP mirror (read-only). Raw copies of SAP documents, written only by sync
 * services. No business logic. See docs/p2p/02-data-model.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p2p_sap_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->integer('status_id')->default(0); // P2pPurchaseOrderStatus, derived
            $table->string('company_code')->nullable(); // BUKRS
            $table->string('purchase_order')->nullable()->index(); // EBELN
            $table->string('purchase_order_item')->nullable(); // EBELP
            $table->string('vendor_code')->nullable(); // LIFNR
            $table->date('purchase_order_date')->nullable(); // BEDAT
            $table->string('purchase_order_type')->nullable(); // BSART
            $table->date('changed_on')->nullable(); // AEDAT
            $table->string('created_by')->nullable(); // ERNAM
            $table->string('currency_key')->nullable(); // WAERS
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->json('gr_numbers')->nullable();
            $table->integer('quantity_po_balance')->nullable();

            // Down payment (read from EKPO / EKBE).
            $table->boolean('has_down_payment')->default(false);
            $table->decimal('down_payment_amount', 15, 2)->nullable();
            $table->decimal('down_payment_percent', 5, 2)->nullable();
            $table->string('down_payment_type', 4)->nullable();
            $table->date('down_payment_due_date')->nullable();
            $table->string('down_payment_status', 16)->nullable(); // P2pDownPaymentStatus
            $table->string('down_payment_document')->nullable();
            $table->timestamp('down_payment_paid_at')->nullable();

            $table->timestamps();

            $table->index(['company_code', 'vendor_code']);
        });

        Schema::create('p2p_sap_good_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_order')->nullable()->index(); // EBELN
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('good_receipt_number')->nullable()->index(); // MBLNR
            $table->string('reference_number')->nullable();
            $table->decimal('amount_total', 15, 2)->nullable();
            $table->date('due_at')->nullable();
            $table->date('upload_at')->nullable();
            $table->date('date_at')->nullable();
            $table->unsignedBigInteger('delivery_type_id')->nullable(); // p2p_delivery_types (no FK: created later)
            $table->timestamps();
        });

        Schema::create('p2p_sap_good_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('p2p_sap_good_receipt_id')->nullable()->constrained('p2p_sap_good_receipts')->nullOnDelete();
            $table->string('purchase_order')->nullable(); // EBELN
            $table->string('purchase_order_item')->nullable(); // EBELP
            $table->integer('item_numbering')->nullable();
            $table->unsignedBigInteger('item_id')->nullable(); // p2p_items (no FK: created later)
            $table->integer('material_doc_year')->nullable(); // MJAHR
            $table->string('material_doc_item', 45)->nullable(); // ZEILE
            $table->string('storage_location')->nullable(); // LGORT
            $table->string('item_name')->nullable();
            $table->string('item_description')->nullable();
            $table->decimal('quantity_po', 15, 3)->nullable();
            $table->decimal('quantity_served', 15, 3)->nullable();
            $table->string('uom')->nullable();
            $table->integer('gr')->nullable();
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->integer('quantity_po_balance')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p2p_sap_good_receipt_items');
        Schema::dropIfExists('p2p_sap_good_receipts');
        Schema::dropIfExists('p2p_sap_purchase_orders');
    }
};
