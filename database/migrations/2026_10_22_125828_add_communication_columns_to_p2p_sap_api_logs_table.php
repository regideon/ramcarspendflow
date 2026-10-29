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
        Schema::table('p2p_sap_api_logs', function (Blueprint $table) {
            // Communication audit: SOAP writes and OData reads share one table.
            $table->string('type', 10)->default('soap')->after('id')->index();
            $table->text('error_message')->nullable()->after('http_status');
            $table->foreignId('user_id')->nullable()->after('p2p_invoice_id')->constrained()->nullOnDelete();

            $table->index(['type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('p2p_sap_api_logs', function (Blueprint $table) {
            $table->dropIndex(['type', 'created_at']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['type', 'error_message']);
        });
    }
};
