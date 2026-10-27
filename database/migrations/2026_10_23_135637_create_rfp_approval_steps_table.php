<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An RFP's own approval route, copied from the workflow (with the matrix tier expanded)
 * when it is submitted, so later workflow or matrix edits never change it. Each
 * resubmission adds a round; steps sharing a sequence run in parallel.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rfp_approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfp_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('round');
            $table->unsignedSmallInteger('sequence');
            $table->unsignedTinyInteger('stage_position');
            $table->string('kind', 20); // RfpStageKind
            $table->string('role', 40)->nullable(); // RoleName
            $table->string('status', 30); // RfpStatus
            $table->string('label', 60);
            $table->boolean('can_return_to_requestor')->default(false);
            $table->unsignedTinyInteger('return_to_position')->nullable();
            $table->boolean('can_reject')->default(false);
            $table->boolean('can_cancel')->default(false);
            $table->string('state', 15); // RfpApprovalStepState
            $table->foreignId('acted_by')->nullable()->constrained('users');
            $table->timestamp('acted_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['rfp_id', 'round', 'sequence']);
            $table->index(['state', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfp_approval_steps');
    }
};
