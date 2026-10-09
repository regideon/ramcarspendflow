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
        Schema::create('rfp_one_time_vendors', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150)->nullable();
            $table->string('name', 150);
            $table->string('street', 150)->nullable();
            $table->string('city', 150)->nullable();
            $table->string('tin', 15)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfp_one_time_vendors');
    }
};
