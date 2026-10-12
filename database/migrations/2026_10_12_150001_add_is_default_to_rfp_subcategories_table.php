<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The sub category pre-selected on new RFP lines (Travel Expense › OPEX - Domestic,
 * legacy id 187, to start; Admins can change it).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rfp_subcategories', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('requires_po_number');
        });

        DB::table('rfp_subcategories')
            ->where('name', 'OPEX - Domestic')
            ->whereIn('rfp_category_id', DB::table('rfp_categories')->where('name', 'Travel Expense')->select('id'))
            ->update(['is_default' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfp_subcategories', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
