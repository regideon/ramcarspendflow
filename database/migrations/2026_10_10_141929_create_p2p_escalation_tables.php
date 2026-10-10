<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data-driven escalation routes (reasons + ordered hops) and per-GR escalations.
 * See docs/p2p/03-master-data.md §3.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p2p_escalation_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('origin_role')->nullable(); // vendor | processor | any
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('p2p_escalation_reason_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('p2p_escalation_reason_id')->constrained('p2p_escalation_reasons')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('role'); // warehouse | procurement | vendor | processor | origin
            $table->unsignedBigInteger('status_id')->nullable(); // P2pStatus set at this hop
            $table->string('action_label')->nullable();
            $table->string('action')->nullable(); // custom StepAction (e.g. reverse & re-GR)
            $table->boolean('override_header_status')->default(false);
            $table->timestamps();
        });

        Schema::create('p2p_escalations', function (Blueprint $table) {
            $table->id();
            $table->string('escalation_number')->unique();
            $table->string('batch_number')->nullable()->index();
            $table->foreignId('p2p_invoice_id')->constrained('p2p_invoices')->cascadeOnDelete();
            $table->foreignId('p2p_good_receipt_state_id')->nullable()
                ->constrained('p2p_good_receipt_states')->nullOnDelete();
            $table->foreignId('p2p_escalation_reason_id')->constrained('p2p_escalation_reasons');
            $table->unsignedSmallInteger('current_step_order')->default(1);
            $table->string('current_role')->nullable();
            $table->string('origin_role')->nullable();
            $table->string('status')->default('open'); // open | resolved | cancelled
            $table->integer('from_status')->nullable(); // P2pStatus to return to on resolve
            $table->text('remarks')->nullable();
            $table->foreignId('actioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p2p_escalations');
        Schema::dropIfExists('p2p_escalation_reason_steps');
        Schema::dropIfExists('p2p_escalation_reasons');
    }
};
