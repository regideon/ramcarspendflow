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
        Schema::create('rfp_due_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // stable key for code, e.g. urgent; never branch on id
            $table->string('name', 50);
            $table->string('description', 150)->nullable();
            $table->unsignedSmallInteger('processing_days');
            $table->boolean('business_days_only')->default(true); // skip Saturdays and Sundays
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfp_due_types');
    }
};
