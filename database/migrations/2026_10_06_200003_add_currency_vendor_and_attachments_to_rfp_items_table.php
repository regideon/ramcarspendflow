<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-line currency, PHP amount, reimbursement vendor and supporting documents,
 * as on the legacy RFP form.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rfp_items', function (Blueprint $table) {
            // Reimbursement only: who the requestor actually paid
            $table->foreignId('company_id')->nullable()->after('sort')->constrained();
            $table->string('vendor_type', 20)->nullable()->after('company_id'); // RfpVendorType
            $table->foreignId('vendor_id')->nullable()->after('vendor_type')->constrained();
            $table->foreignId('rfp_one_time_vendor_id')->nullable()->after('vendor_id')->constrained();

            $table->foreignId('currency_id')->nullable()->after('description')->constrained();
            $table->decimal('exchange_rate', 18, 6)->default(0)->after('amount'); // to PHP, captured when saved; 0 = no rate on file
            $table->decimal('php_amount', 15, 2)->default(0)->after('exchange_rate');
            $table->json('attachments')->nullable()->after('php_amount'); // paths on the private disk
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfp_items', function (Blueprint $table) {
            $table->dropColumn(['vendor_type', 'exchange_rate', 'php_amount', 'attachments']);
            $table->dropConstrainedForeignId('currency_id');
            $table->dropConstrainedForeignId('rfp_one_time_vendor_id');
            $table->dropConstrainedForeignId('vendor_id');
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
