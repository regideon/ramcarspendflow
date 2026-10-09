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
        Schema::create('cost_center_gl_account', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cost_center_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gl_account_id')->constrained()->cascadeOnDelete();
            $table->string('expense_group', 50); // e.g. Operating Expenses, Manufacturing Expenses
            $table->string('category', 100)->nullable();
            $table->string('account_type', 30)->default('Accounts Payable');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['cost_center_id', 'gl_account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_center_gl_account');
    }
};
