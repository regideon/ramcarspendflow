<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A supplier login may be linked to several SAP vendor codes (e.g. a local
     * supplier record and a service-provider record for the same legal entity).
     * The original single `users.vendor_id` cannot express that, so move the
     * relationship into a pivot and backfill each user's existing vendor.
     *
     * `users.vendor_id` is kept as the cached "primary" vendor for backwards
     * compatibility with existing lookups.
     */
    public function up(): void
    {
        Schema::create('vendor_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'vendor_id']);
        });

        DB::table('users')
            ->whereNotNull('vendor_id')
            ->join('vendors', 'vendors.id', '=', 'users.vendor_id')
            ->orderBy('users.id')
            ->select(['users.id as user_id', 'users.vendor_id'])
            ->chunk(500, function ($users): void {
                $rows = $users->map(fn ($user): array => [
                    'user_id' => $user->user_id,
                    'vendor_id' => $user->vendor_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all();

                DB::table('vendor_user')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_user');
    }
};
