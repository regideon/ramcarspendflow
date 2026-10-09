<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Company;
use App\Models\Rfp\ApprovalRuleStep;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\Rfp\ApprovalMatrixSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles plus one demo login per role, for local testing and UAT.
 *
 * Every run truncates users, roles, their pivot tables and the users' RFPs first,
 * so it always produces the same data. Run it alone with:
 *   php artisan db:seed --class=RoleAndUserSeeder
 */
class RoleAndUserSeeder extends Seeder
{
    public const string PASSWORD = 'TestPass123!';

    public const string OWNER_EMAIL = 'reg.ebalobor@kaisa.com';

    public const string DEMO_VENDOR_CODE = 'DEMO000001';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('RoleAndUserSeeder truncates users and roles and must not run in production.');
        }

        $this->truncate();
        $this->seedRoles();
        $this->seedUsers();

        // Truncating roles leaves the approval matrix pointing at old role ids; rebuild it.
        if (Schema::hasTable('rfp_approval_rules') && DB::table('rfp_approval_rules')->exists()) {
            $this->call(ApprovalMatrixSeeder::class);
        }
    }

    /**
     * Empty users, roles and everything that hangs off them.
     */
    public function truncate(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            // Children first, so this also works where foreign keys stay enforced (e.g. SQLite in a transaction).
            ApprovalRuleStep::query()->delete();

            foreach ([
                // RFP transactions belong to users, so they go too.
                'rfp_histories',
                'rfp_items',
                'rfps',
                'company_user',
                'model_has_roles',
                'model_has_permissions',
                'role_has_permissions',
                'roles',
                'permissions',
                'sessions',
                'password_reset_tokens',
                'users',
            ] as $table) {
                DB::table($table)->truncate();
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function seedRoles(): void
    {
        foreach (RoleName::cases() as $roleName) {
            Role::findOrCreate($roleName->value, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function seedUsers(): void
    {
        $companies = Company::query()->orderBy('code')->get();
        $vendor = Vendor::query()->firstOrCreate(
            ['code' => self::DEMO_VENDOR_CODE],
            ['name' => 'Demo Vendor Inc.', 'email' => 'vendor@ramcar.com', 'country' => 'PH', 'currency' => 'PHP'],
        );

        // The owner account always comes first (id 1).
        $this->createUser('Reg Ebalobor', self::OWNER_EMAIL, RoleName::Admin, $companies);

        foreach (RoleName::cases() as $roleName) {
            $this->createUser(
                name: "Demo {$roleName->value}",
                email: self::emailFor($roleName),
                roleName: $roleName,
                companies: $companies,
                vendor: $roleName === RoleName::Vendor ? $vendor : null,
            );
        }
    }

    /**
     * @param  Collection<int, Company>  $companies
     */
    protected function createUser(string $name, string $email, RoleName $roleName, $companies, ?Vendor $vendor = null): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => self::PASSWORD,
            'company_id' => $companies->first()?->id,
            'vendor_id' => $vendor?->id,
            'is_active' => true,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole($roleName);
        $user->companies()->sync($companies->pluck('id'));

        return $user;
    }

    /**
     * Generic login for a role, e.g. "AP Supervisor" => ap.supervisor@ramcar.com.
     */
    public static function emailFor(RoleName $roleName): string
    {
        return Str::of($roleName->value)
            ->remove('&')
            ->replace('/', ' ')
            ->slug('.')
            ->append('@ramcar.com')
            ->toString();
    }
}
