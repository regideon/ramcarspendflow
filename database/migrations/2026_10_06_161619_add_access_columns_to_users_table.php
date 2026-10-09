<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('password')->constrained()->nullOnDelete(); // home company
            $table->foreignId('vendor_id')->nullable()->after('company_id')->constrained()->nullOnDelete(); // set for Vendor (supplier) logins
            $table->boolean('is_active')->default(true)->after('vendor_id');

            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_id');
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn('is_active');
        });
    }
};
