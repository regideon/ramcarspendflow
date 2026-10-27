<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAP amounts routinely exceed decimal(12,4): real POs in the 10-month backfill
 * reached 4.8 billion PHP, which overflowed the mirror's `total_amount` and
 * aborted the sync. Widen every SAP-sourced money column to decimal(18,4),
 * which holds up to 99,999,999,999,999.9999 without dropping the 4 decimals
 * the mirror keeps for unit costs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('p2p_sap_purchase_orders', function (Blueprint $table): void {
            $table->decimal('total_amount', 18, 4)->nullable()->change();
        });

        Schema::table('p2p_sap_good_receipts', function (Blueprint $table): void {
            $table->decimal('amount_total', 18, 4)->nullable()->change();
        });

        Schema::table('p2p_sap_good_receipt_items', function (Blueprint $table): void {
            $table->decimal('unit_cost', 18, 4)->nullable()->change();
            $table->decimal('amount', 18, 4)->nullable()->change();
        });

        Schema::table('p2p_good_receipt_states', function (Blueprint $table): void {
            // Working table keeps 2 decimals; only the integer part needs room.
            $table->decimal('amount_total', 18, 2)->default(0)->change();
        });
    }

    public function down(): void
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

        Schema::table('p2p_good_receipt_states', function (Blueprint $table): void {
            $table->decimal('amount_total', 12, 2)->default(0)->change();
        });
    }
};
