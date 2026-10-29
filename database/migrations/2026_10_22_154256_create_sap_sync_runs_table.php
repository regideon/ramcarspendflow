<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p2p_sap_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('vendor_code')->nullable()->index();
            $table->date('window_from')->nullable();
            $table->date('window_to')->nullable();
            $table->json('filters')->nullable();
            $table->string('status', 16)->default('running')->index();
            $table->unsignedInteger('discovered')->default(0);
            $table->unsignedInteger('synced')->default(0);
            $table->unsignedInteger('skipped_unknown_vendor')->default(0);
            $table->unsignedInteger('skipped_unknown_company')->default(0);
            $table->unsignedInteger('created_vendors')->default(0);
            $table->unsignedInteger('reversals')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p2p_sap_sync_runs');
    }
};
