<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 4 — SAP API audit log (SOAP write calls). Passwords are redacted before
 * storage. See docs/p2p/07-sap-integration.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p2p_sap_api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint')->nullable();
            $table->longText('request')->nullable();
            $table->longText('response')->nullable();
            $table->integer('http_status')->nullable();
            $table->decimal('execution_time', 10, 3)->nullable();
            $table->foreignId('p2p_invoice_id')->nullable()->constrained('p2p_invoices')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p2p_sap_api_logs');
    }
};
