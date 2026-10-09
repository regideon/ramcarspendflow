<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per tier of the Step 3 approval matrix. A tier covers amounts above the
     * previous tier's max_amount up to and including its own (null = no upper limit).
     */
    public function up(): void
    {
        Schema::create('rfp_approval_rules', function (Blueprint $table) {
            $table->id();
            $table->string('request_type', 20)->nullable(); // RfpRequestType; null = any type
            $table->boolean('is_direct_to_vp')->default(false);
            $table->decimal('max_amount', 15, 2)->nullable(); // PHP, inclusive
            $table->string('description', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['request_type', 'is_direct_to_vp', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfp_approval_rules');
    }
};
