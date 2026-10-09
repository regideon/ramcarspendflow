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
        Schema::create('rfp_approval_rule_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfp_approval_rule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('step_order'); // steps sharing an order run in parallel
            $table->foreignId('role_id')->constrained('roles'); // restrict: a role in use cannot be deleted
            $table->timestamps();

            $table->unique(['rfp_approval_rule_id', 'role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfp_approval_rule_steps');
    }
};
