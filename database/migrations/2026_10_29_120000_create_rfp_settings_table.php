<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RFP-wide settings Admins can change from RFP Settings. One row.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rfp_settings', function (Blueprint $table) {
            $table->id();
            // Mass approval: RFPs of this amount and above must be approved one by one
            $table->decimal('bulk_approval_item_limit', 15, 2)->default(200000);
            // Mass approval: the most one batch may add up to
            $table->decimal('bulk_approval_total_limit', 15, 2)->default(500000);
            $table->timestamps();
        });

        DB::table('rfp_settings')->insert(['created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfp_settings');
    }
};
