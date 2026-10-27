<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The approval round in progress, and the PHP total its matrix tier was resolved for
 * (a resubmission with a changed amount goes through the matrix again).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rfps', function (Blueprint $table) {
            $table->unsignedTinyInteger('approval_round')->default(0)->after('current_approver_id');
            $table->decimal('routed_php_amount', 15, 2)->nullable()->after('approval_round');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfps', function (Blueprint $table) {
            $table->dropColumn(['approval_round', 'routed_php_amount']);
        });
    }
};
