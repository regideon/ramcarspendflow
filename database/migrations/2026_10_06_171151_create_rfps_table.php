<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rfps', function (Blueprint $table) {
            $table->id();
            $table->string('rfp_number', 20)->nullable()->unique(); // RFP-YYYY-000001, set on create
            $table->foreignId('company_id')->constrained();
            $table->foreignId('requestor_id')->constrained('users');
            $table->string('request_type', 20); // RfpRequestType
            $table->string('payee_type', 20); // RfpPayeeType
            $table->foreignId('vendor_id')->nullable()->constrained();
            $table->foreignId('rfp_one_time_vendor_id')->nullable()->constrained();
            $table->foreignId('currency_id')->constrained();
            $table->decimal('exchange_rate', 18, 6)->default(1); // to PHP, captured when saved
            $table->decimal('total_amount', 15, 2)->default(0); // in the RFP currency
            $table->decimal('total_php_amount', 15, 2)->default(0); // drives the approval matrix
            $table->foreignId('payment_method_id')->nullable()->constrained();
            $table->date('due_date')->nullable();
            $table->text('purpose');
            $table->boolean('is_direct_to_vp')->default(false);
            $table->json('attachments')->nullable(); // paths on the private disk
            $table->string('status', 30)->default('draft')->index(); // RfpStatus
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['requestor_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfps');
    }
};
