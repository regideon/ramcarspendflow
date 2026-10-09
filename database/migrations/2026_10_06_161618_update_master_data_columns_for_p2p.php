<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widens the shared master data so the P2P module can use it as-is:
 * SAP-synced companies and vendors arrive without RFP-only fields.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('short_name', 10)->nullable()->change();
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->foreignId('account_group_id')->nullable()->change();
            $table->string('email')->nullable()->after('name');
            $table->string('tax_number', 20)->nullable()->after('country'); // TIN (STCD1)
            $table->string('currency', 5)->nullable()->after('tax_number'); // WAERS
            $table->timestamp('last_po_synced_at')->nullable()->after('is_active');
        });

        Schema::table('tax_codes', function (Blueprint $table) {
            $table->string('category', 20)->default('taxable')->after('description'); // TaxCodeCategory
        });

        Schema::table('withholding_tax_codes', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['withholding_tax_type_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('withholding_tax_codes', function (Blueprint $table) {
            $table->dropUnique(['withholding_tax_type_id', 'code']);
            $table->unique(['code']);
        });

        Schema::table('tax_codes', function (Blueprint $table) {
            $table->dropColumn('category');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['email', 'tax_number', 'currency', 'last_po_synced_at']);
            $table->foreignId('account_group_id')->nullable(false)->change();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('short_name', 10)->nullable(false)->change();
        });
    }
};
