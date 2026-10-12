<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Read invoice with AI: what each billed line was matched to on the vendor's invoice, and a
 * summary per submission, so the AP Processor is warned about lines that do not match.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('p2p_invoice_lines', function (Blueprint $table) {
            $table->string('invoice_reference', 50)->nullable()->after('amount'); // the vendor's invoice no.
            $table->decimal('invoice_unit_price', 15, 4)->nullable()->after('invoice_reference');
            $table->char('invoice_currency', 3)->nullable()->after('invoice_unit_price');
            $table->string('match_status', 20)->nullable()->after('invoice_currency'); // P2pThreeWayMatchStatus
            $table->json('match_reasons')->nullable()->after('match_status');
            $table->boolean('is_invoice_verified')->default(false)->after('match_reasons'); // linked to a line read from the invoice
            $table->boolean('is_quantity_edited')->default(false)->after('is_invoice_verified'); // vendor changed the read Inv Qty
        });

        Schema::table('p2p_invoices', function (Blueprint $table) {
            $table->json('invoice_match')->nullable()->after('attachments'); // warnings, unmatched lines, charges, invoices read, vendor remarks
            $table->unsignedSmallInteger('match_flag_count')->default(0)->after('invoice_match'); // lines AP should check
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('p2p_invoices', function (Blueprint $table) {
            $table->dropColumn(['invoice_match', 'match_flag_count']);
        });

        Schema::table('p2p_invoice_lines', function (Blueprint $table) {
            $table->dropColumn(['invoice_reference', 'invoice_unit_price', 'invoice_currency', 'match_status', 'match_reasons', 'is_invoice_verified', 'is_quantity_edited']);
        });
    }
};
