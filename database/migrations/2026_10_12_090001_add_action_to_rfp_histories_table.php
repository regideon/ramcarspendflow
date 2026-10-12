<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * History rows can record an action ("Approved", "Tax codes processed") that does not
 * change the status, as the legacy RFP Portal history does.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rfp_histories', function (Blueprint $table) {
            $table->string('action', 40)->nullable()->after('user_id');
            $table->string('to_status', 30)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfp_histories', function (Blueprint $table) {
            $table->dropColumn('action');
            $table->string('to_status', 30)->nullable(false)->change();
        });
    }
};
