<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 2 — working / transactional tables (normalized).
 *
 * p2p_invoices own the authoritative workflow status; p2p_invoice_lines bill by
 * quantity against the SAP mirror; p2p_good_receipt_states is a derived projection.
 * See docs/p2p/02-data-model.md and 06-workflow-statuses.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p2p_invoices', function (Blueprint $table) {
            $table->id();
            $table->integer('status')->default(1)->index(); // P2pStatus
            $table->string('grc_number')->nullable()->unique(); // GRC-00000001
            $table->string('code')->nullable()->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->json('po_numbers')->nullable();
            $table->json('gr_numbers')->nullable();
            $table->json('reference_number')->nullable();
            $table->string('clearing_doc_no')->nullable(); // AUGBL
            $table->string('fi_doc_no')->nullable(); // BELNR
            $table->string('invoice_number')->nullable();
            $table->decimal('quantity_submitted', 15, 2)->default(0);
            $table->decimal('amount_submitted', 15, 2)->default(0);
            $table->boolean('processed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->string('withholding_tax_code')->nullable();
            $table->decimal('withholding_tax_base', 15, 2)->nullable();
            $table->string('tax_code')->nullable();
            $table->string('remarks_ap')->nullable();
            $table->string('remarks_checker')->nullable();
            $table->timestamp('invoice_at')->nullable();
            $table->integer('fiscal_year')->nullable();
            $table->json('attachments')->nullable(); // paths on the private disk
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('warning_sent_at')->nullable();
            $table->date('upload_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            // Tax summary (computed by P2pTaxSummaryService).
            $table->decimal('vatable_sales', 15, 2)->default(0);
            $table->decimal('vat_exempt', 15, 2)->default(0);
            $table->decimal('vat_zero_rated', 15, 2)->default(0);
            $table->decimal('vat', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('ewt', 15, 2)->default(0);
            $table->decimal('net', 15, 2)->default(0);

            $table->timestamps();

            $table->index(['vendor_id', 'status']);
        });

        Schema::create('p2p_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('p2p_invoice_id')->constrained('p2p_invoices')->cascadeOnDelete();
            $table->foreignId('p2p_sap_good_receipt_item_id')->nullable()
                ->constrained('p2p_sap_good_receipt_items')->nullOnDelete();
            $table->string('purchase_order')->nullable();
            $table->string('purchase_order_item')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('storage_location')->nullable();
            $table->string('material_type')->nullable()->index(); // P2pMaterialType
            $table->foreignId('classified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('classified_at')->nullable();
            $table->string('item_name')->nullable();
            $table->string('item_description')->nullable();
            $table->decimal('quantity_po', 15, 3)->nullable();
            $table->decimal('quantity_billed', 15, 3)->default(0);
            $table->decimal('quantity_previous', 15, 3)->default(0);
            $table->decimal('quantity_remaining', 15, 3)->default(0);
            $table->boolean('is_fully_billed')->default(false);
            $table->string('uom')->nullable();
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('p2p_good_receipt_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('p2p_sap_good_receipt_id')->nullable()
                ->constrained('p2p_sap_good_receipts')->nullOnDelete();
            $table->string('purchase_order')->nullable();
            $table->string('good_receipt_number')->nullable()->index();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('status')->default(1)->index(); // derived P2pStatus
            $table->decimal('quantity_total', 15, 3)->default(0);
            $table->decimal('quantity_served', 15, 3)->default(0);
            $table->decimal('amount_total', 15, 2)->default(0);
            $table->boolean('is_fully_processed')->default(false);
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_document')->nullable();
            $table->string('replacement_gr_number')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('p2p_approval_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('p2p_invoice_id')->nullable()->constrained('p2p_invoices')->cascadeOnDelete();
            $table->foreignId('actioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('from_status')->nullable(); // P2pStatus
            $table->integer('to_status')->nullable(); // P2pStatus
            $table->text('remarks')->nullable();
            $table->boolean('is_visible_to_vendor')->default(false);
            $table->boolean('is_visible_to_admin')->default(false);
            $table->string('escalation_reason')->nullable();
            $table->json('escalated_items')->nullable();
            $table->string('action_label')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p2p_approval_histories');
        Schema::dropIfExists('p2p_good_receipt_states');
        Schema::dropIfExists('p2p_invoice_lines');
        Schema::dropIfExists('p2p_invoices');
    }
};
