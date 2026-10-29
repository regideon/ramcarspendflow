<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partial GR splitting keeps the SAP mirror item on the base GR and clones the
 * selected quantity onto a `-P{n}` split. These columns track what has been split
 * out (so a re-sync does not restore it) and what has been merged back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('p2p_sap_good_receipt_items', function (Blueprint $table): void {
            $table->integer('quantity_split_out')->default(0)->after('quantity_served');
            $table->unsignedBigInteger('split_from_item_id')->nullable()->after('quantity_split_out')->index();
            $table->timestamp('merged_at')->nullable()->after('split_from_item_id');
        });

        Schema::table('p2p_good_receipt_states', function (Blueprint $table): void {
            $table->timestamp('merged_at')->nullable()->after('is_fully_processed');
            $table->string('merged_into_good_receipt_number')->nullable()->after('merged_at');
        });
    }

    public function down(): void
    {
        Schema::table('p2p_good_receipt_states', function (Blueprint $table): void {
            $table->dropColumn(['merged_at', 'merged_into_good_receipt_number']);
        });

        Schema::table('p2p_sap_good_receipt_items', function (Blueprint $table): void {
            $table->dropColumn(['quantity_split_out', 'split_from_item_id', 'merged_at']);
        });
    }
};
