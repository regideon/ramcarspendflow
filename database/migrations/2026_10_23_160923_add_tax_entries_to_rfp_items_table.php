<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tax entries the AP Processor books at Step 4 (legacy "Process Tax Codes"), per line
 * and in the line's own currency: input tax and withholding tax codes, and the VAT /
 * withholding amounts worked out from the (VAT-inclusive) gross amount.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rfp_items', function (Blueprint $table) {
            $table->foreignId('tax_code_id')->nullable()->after('php_amount')->constrained();
            $table->foreignId('withholding_tax_code_id')->nullable()->after('tax_code_id')->constrained();
            $table->foreignId('withholding_tax_type_id')->nullable()->after('withholding_tax_code_id')->constrained();
            $table->decimal('vat_amount', 15, 2)->nullable()->after('withholding_tax_type_id');
            $table->decimal('net_of_vat_amount', 15, 2)->nullable()->after('vat_amount');
            $table->decimal('withholding_tax_amount', 15, 2)->nullable()->after('net_of_vat_amount');
            $table->decimal('net_of_withholding_tax_amount', 15, 2)->nullable()->after('withholding_tax_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfp_items', function (Blueprint $table) {
            $table->dropColumn(['vat_amount', 'net_of_vat_amount', 'withholding_tax_amount', 'net_of_withholding_tax_amount']);
            $table->dropConstrainedForeignId('withholding_tax_type_id');
            $table->dropConstrainedForeignId('withholding_tax_code_id');
            $table->dropConstrainedForeignId('tax_code_id');
        });
    }
};
