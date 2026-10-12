<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The PO item's price (SAP EKPO-NETPR per EKPO-PEINH units, in EKKO-WAERS), for the 3-way
 * match against the vendor's invoice "exact to the centavo". unit_cost stays the GR value
 * in pesos (MSEG-DMBTR / ERFMG), which cannot be compared with a foreign-currency invoice.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('p2p_sap_good_receipt_items', function (Blueprint $table) {
            $table->decimal('net_price', 15, 4)->nullable()->after('unit_cost');
            $table->unsignedInteger('price_unit')->default(1)->after('net_price');
            $table->char('currency', 3)->nullable()->after('price_unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('p2p_sap_good_receipt_items', function (Blueprint $table) {
            $table->dropColumn(['net_price', 'price_unit', 'currency']);
        });
    }
};
