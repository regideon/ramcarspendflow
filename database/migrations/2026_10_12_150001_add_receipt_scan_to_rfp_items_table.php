<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the AI read from the line's receipt (Read receipt), kept for audit next to
 * what the requestor actually submitted.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rfp_items', function (Blueprint $table) {
            $table->json('receipt_scan')->nullable()->after('attachments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfp_items', function (Blueprint $table) {
            $table->dropColumn('receipt_scan');
        });
    }
};
