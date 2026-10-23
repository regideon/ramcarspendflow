<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment type set by the AP Manager at the final accounting review (legacy `ids_type`):
 * IDS, or Non-IDS (manual). Legacy defaulted to IDS.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rfps', function (Blueprint $table) {
            $table->boolean('is_ids')->default(true)->after('routed_php_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfps', function (Blueprint $table) {
            $table->dropColumn('is_ids');
        });
    }
};
