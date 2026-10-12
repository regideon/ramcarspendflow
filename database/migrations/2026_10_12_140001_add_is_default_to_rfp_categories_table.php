<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The category pre-selected on new RFP lines (Travel Expense to start; Admins can change it).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rfp_categories', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('name');
        });

        DB::table('rfp_categories')->where('name', 'Travel Expense')->update(['is_default' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfp_categories', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
