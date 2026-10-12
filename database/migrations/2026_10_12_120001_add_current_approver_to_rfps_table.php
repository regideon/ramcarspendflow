<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who must act on the RFP next, for the RFP Approvals queue: a role (RoleName), and
 * optionally the specific person. When no person is set, anyone with the role may act.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rfps', function (Blueprint $table) {
            $table->string('current_approver_role', 40)->nullable()->after('status');
            $table->foreignId('current_approver_id')->nullable()->after('current_approver_role')->constrained('users');

            $table->index(['status', 'current_approver_role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfps', function (Blueprint $table) {
            $table->dropIndex(['status', 'current_approver_role']);
            $table->dropConstrainedForeignId('current_approver_id');
            $table->dropColumn('current_approver_role');
        });
    }
};
