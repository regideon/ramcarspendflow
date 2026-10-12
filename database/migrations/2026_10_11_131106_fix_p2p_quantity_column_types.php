<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quantity columns are integers, matching the P2P source (SAP goods-receipt and
 * purchase-order quantities are whole units). Only the aggregate/decimal columns
 * (`quantity_previously_served`, `quantity_remaining`, `quantity_submitted`) stay
 * decimal(15,2), exactly as in the legacy schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('p2p_sap_purchase_orders', function (Blueprint $table): void {
            $table->integer('quantity_po_balance')->nullable()->change();
        });

        Schema::table('p2p_sap_good_receipt_items', function (Blueprint $table): void {
            $table->integer('quantity_po')->nullable()->change();
            $table->integer('quantity_served')->nullable()->change();
            $table->integer('quantity_po_balance')->nullable()->change();
        });

        Schema::table('p2p_invoice_lines', function (Blueprint $table): void {
            $table->integer('quantity_po')->nullable()->change();
            $table->integer('quantity_billed')->default(0)->change();
            $table->decimal('quantity_previous', 15, 2)->default(0)->change();
            $table->decimal('quantity_remaining', 15, 2)->default(0)->change();
        });

        Schema::table('p2p_good_receipt_states', function (Blueprint $table): void {
            $table->integer('quantity_total')->default(0)->change();
            $table->integer('quantity_served')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('p2p_sap_purchase_orders', function (Blueprint $table): void {
            $table->decimal('quantity_po_balance', 15, 3)->nullable()->change();
        });

        Schema::table('p2p_sap_good_receipt_items', function (Blueprint $table): void {
            $table->decimal('quantity_po', 15, 3)->nullable()->change();
            $table->decimal('quantity_served', 15, 3)->nullable()->change();
            $table->decimal('quantity_po_balance', 15, 3)->nullable()->change();
        });

        Schema::table('p2p_invoice_lines', function (Blueprint $table): void {
            $table->decimal('quantity_po', 15, 3)->nullable()->change();
            $table->decimal('quantity_billed', 15, 3)->default(0)->change();
            $table->decimal('quantity_previous', 15, 3)->default(0)->change();
            $table->decimal('quantity_remaining', 15, 3)->default(0)->change();
        });

        Schema::table('p2p_good_receipt_states', function (Blueprint $table): void {
            $table->decimal('quantity_total', 15, 3)->default(0)->change();
            $table->decimal('quantity_served', 15, 3)->default(0)->change();
        });
    }
};
