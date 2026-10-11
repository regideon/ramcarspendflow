<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Align money precision with the P2P source: SAP mirror amounts are decimal(12,4),
 * working tables decimal(12,2), and submission headers decimal(15,2) (unchanged).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('p2p_sap_purchase_orders', function (Blueprint $table): void {
            $table->decimal('total_amount', 12, 4)->nullable()->change();
        });

        Schema::table('p2p_sap_good_receipts', function (Blueprint $table): void {
            $table->decimal('amount_total', 12, 4)->nullable()->change();
        });

        Schema::table('p2p_sap_good_receipt_items', function (Blueprint $table): void {
            $table->decimal('unit_cost', 12, 4)->nullable()->change();
            $table->decimal('amount', 12, 4)->nullable()->change();
        });

        Schema::table('p2p_invoice_lines', function (Blueprint $table): void {
            $table->decimal('unit_cost', 12, 2)->nullable()->change();
            $table->decimal('amount', 12, 2)->nullable()->change();
        });

        Schema::table('p2p_good_receipt_states', function (Blueprint $table): void {
            $table->decimal('amount_total', 12, 2)->default(0)->change();
        });

        Schema::table('p2p_items', function (Blueprint $table): void {
            $table->decimal('unit_cost', 12, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('p2p_sap_purchase_orders', function (Blueprint $table): void {
            $table->decimal('total_amount', 15, 2)->nullable()->change();
        });

        Schema::table('p2p_sap_good_receipts', function (Blueprint $table): void {
            $table->decimal('amount_total', 15, 2)->nullable()->change();
        });

        Schema::table('p2p_sap_good_receipt_items', function (Blueprint $table): void {
            $table->decimal('unit_cost', 15, 2)->nullable()->change();
            $table->decimal('amount', 15, 2)->nullable()->change();
        });

        Schema::table('p2p_invoice_lines', function (Blueprint $table): void {
            $table->decimal('unit_cost', 15, 2)->nullable()->change();
            $table->decimal('amount', 15, 2)->nullable()->change();
        });

        Schema::table('p2p_good_receipt_states', function (Blueprint $table): void {
            $table->decimal('amount_total', 15, 2)->default(0)->change();
        });

        Schema::table('p2p_items', function (Blueprint $table): void {
            $table->decimal('unit_cost', 15, 2)->nullable()->change();
        });
    }
};
