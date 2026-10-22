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
        Schema::table('p2p_invoices', function (Blueprint $table) {
            // PendingPost (21) is the workflow status while SAP is processing the
            // park. These columns keep the last failure and attempt count so AP can
            // retry without changing the legacy status meaning.
            $table->unsignedTinyInteger('sap_park_attempts')->default(0)->after('fiscal_year');
            $table->text('sap_park_error')->nullable()->after('sap_park_attempts');
            $table->timestamp('sap_parked_at')->nullable()->after('sap_park_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('p2p_invoices', function (Blueprint $table) {
            $table->dropColumn(['sap_park_attempts', 'sap_park_error', 'sap_parked_at']);
        });
    }
};
