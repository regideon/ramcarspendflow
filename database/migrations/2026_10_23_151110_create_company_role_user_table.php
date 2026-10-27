<?php

use App\Enums\RoleName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which approval roles a user holds in which company (e.g. AP Processor for 1000 and
 * 2000, Line Manager in 1000 but Department Head in 3000). Approval routing uses this;
 * Spatie roles still grant the screens. Existing users get their current approval
 * roles in each of their companies (or their home company).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 40); // RoleName, one of RoleName::companyScoped()
            $table->timestamps();

            $table->unique(['company_id', 'user_id', 'role']);
            $table->index(['company_id', 'role']);
        });

        $roles = array_map(fn (RoleName $role): string => $role->value, RoleName::companyScoped());

        $holders = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->join('users', 'users.id', '=', 'model_has_roles.model_id')
            ->where('model_has_roles.model_type', 'like', '%User')
            ->whereIn('roles.name', $roles)
            ->get(['users.id as user_id', 'users.company_id as home_company_id', 'roles.name as role']);

        $companiesByUser = DB::table('company_user')->get(['user_id', 'company_id'])->groupBy('user_id');

        foreach ($holders as $holder) {
            $companyIds = $companiesByUser->get($holder->user_id)?->pluck('company_id')->all()
                ?? array_filter([$holder->home_company_id]);

            DB::table('company_role_user')->insertOrIgnore(array_map(fn (int $companyId): array => [
                'company_id' => $companyId,
                'user_id' => $holder->user_id,
                'role' => $holder->role,
                'created_at' => now(),
                'updated_at' => now(),
            ], $companyIds));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_role_user');
    }
};
