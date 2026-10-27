<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who created the RFP and who last changed it. Existing RFPs are backfilled with the
 * requestor as creator and the user behind the latest history entry as last updater.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rfps', function (Blueprint $table) {
            $table->foreignId('created_by_id')->nullable()->after('requestor_id')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->after('created_by_id')->constrained('users')->nullOnDelete();
        });

        DB::table('rfps')->update([
            'created_by_id' => DB::raw('requestor_id'),
            'updated_by_id' => DB::raw('coalesce((select h.user_id from rfp_histories h where h.rfp_id = rfps.id and h.user_id is not null order by h.id desc limit 1), requestor_id)'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by_id');
            $table->dropConstrainedForeignId('created_by_id');
        });
    }
};
