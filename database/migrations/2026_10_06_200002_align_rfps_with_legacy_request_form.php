<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Header fields of the legacy RFP form: due type, account group + payee, pre-approval.
 * Currency and supporting documents move to the line items.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rfps', function (Blueprint $table) {
            $table->foreignId('rfp_due_type_id')->nullable()->after('request_type')->constrained();
            $table->foreignId('account_group_id')->nullable()->after('rfp_due_type_id')->constrained();
            $table->boolean('is_pre_approved')->default(false)->after('is_direct_to_vp');
            $table->json('pre_approval_documents')->nullable()->after('is_pre_approved'); // paths on the private disk

            $table->dropConstrainedForeignId('rfp_one_time_vendor_id');
            $table->dropConstrainedForeignId('currency_id');
            $table->dropColumn(['payee_type', 'exchange_rate', 'total_amount', 'attachments']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfps', function (Blueprint $table) {
            $table->string('payee_type', 20)->default('vendor')->after('request_type');
            $table->foreignId('rfp_one_time_vendor_id')->nullable()->after('vendor_id')->constrained();
            $table->foreignId('currency_id')->nullable()->after('rfp_one_time_vendor_id')->constrained();
            $table->decimal('exchange_rate', 18, 6)->default(1)->after('currency_id');
            $table->decimal('total_amount', 15, 2)->default(0)->after('exchange_rate');
            $table->json('attachments')->nullable()->after('is_direct_to_vp');

            $table->dropColumn(['is_pre_approved', 'pre_approval_documents']);
            $table->dropConstrainedForeignId('account_group_id');
            $table->dropConstrainedForeignId('rfp_due_type_id');
        });
    }
};
