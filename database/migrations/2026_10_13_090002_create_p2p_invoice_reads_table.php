<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved AI reads of vendor invoice attachments (Read invoice with AI), keyed by the file's
 * SHA-256, so reading the same PDF again reuses the result instead of paying for another call.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('p2p_invoice_reads', function (Blueprint $table) {
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
        Schema::dropIfExists('p2p_invoice_reads');
    }
};
