<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved AI reads of supporting documents (Read receipt with AI), keyed by the file's SHA-256,
 * so reading the same document again reuses the result instead of paying for another AI call.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rfp_receipt_reads', function (Blueprint $table) {
            $table->id();
            $table->char('file_hash', 64);
            $table->unsignedSmallInteger('reader_version');
            $table->string('model', 100)->nullable();
            $table->json('result');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['file_hash', 'reader_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfp_receipt_reads');
    }
};
