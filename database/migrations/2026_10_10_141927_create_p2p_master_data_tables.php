<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 3 — P2P reference / setup tables (under Settings → P2P Settings).
 * See docs/p2p/03-master-data.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p2p_items', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->string('name');
            $table->string('uom')->nullable();
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('p2p_delivery_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('p2p_material_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('color')->default('gray');
            $table->string('role_name')->nullable(); // Spatie role that owns this classification
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('p2p_sloc_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('company_code')->nullable(); // null = global default
            $table->string('plant')->nullable();
            $table->string('storage_location');
            $table->string('description')->nullable();
            $table->foreignId('p2p_material_classification_id')
                ->constrained('p2p_material_classifications')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_code', 'plant', 'storage_location'], 'p2p_sloc_unique');
        });

        Schema::create('p2p_material_classification_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('p2p_material_classification_id');
            // Explicit short name: the generated one exceeds MySQL's 64-char limit.
            $table->foreign('p2p_material_classification_id', 'p2p_mc_user_classification_fk')
                ->references('id')->on('p2p_material_classifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['p2p_material_classification_id', 'user_id'], 'p2p_mc_user_unique');
        });

        Schema::create('p2p_email_notification_receivers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->string('notification_type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p2p_email_notification_receivers');
        Schema::dropIfExists('p2p_material_classification_user');
        Schema::dropIfExists('p2p_sloc_classifications');
        Schema::dropIfExists('p2p_material_classifications');
        Schema::dropIfExists('p2p_delivery_types');
        Schema::dropIfExists('p2p_items');
    }
};
