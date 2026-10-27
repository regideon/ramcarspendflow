<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The global RFP approval workflow: ordered stages from AP review to Treasury. A Matrix
 * stage expands into the approval matrix tier. See docs/12-rfp-dynamic-workflow.md.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rfp_workflow_stages', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('position')->unique();
            $table->string('kind', 20); // RfpStageKind
            $table->string('role', 40)->nullable(); // RoleName; null for the Matrix stage
            $table->string('status', 30); // RfpStatus shown while the RFP is at this stage
            $table->string('label', 60);
            $table->boolean('can_return_to_requestor')->default(false);
            $table->unsignedTinyInteger('return_to_position')->nullable(); // an earlier stage it may send back to
            $table->boolean('can_reject')->default(false);
            $table->boolean('can_cancel')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfp_workflow_stages');
    }
};
