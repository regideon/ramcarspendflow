<?php

use App\Enums\RfpRequestType;
use App\Enums\RoleName;
use App\Models\Rfp\ApprovalRule;
use App\Models\User;
use Database\Seeders\CompanySeeder;
use Database\Seeders\Rfp\ApprovalMatrixSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([CompanySeeder::class, RoleAndUserSeeder::class]);
});

it('creates the owner account first, as Admin', function () {
    $owner = User::query()->orderBy('id')->first();

    expect($owner->email)->toBe(RoleAndUserSeeder::OWNER_EMAIL)
        ->and($owner->hasRole(RoleName::Admin))->toBeTrue();
});

it('creates one generic login per role', function (RoleName $roleName) {
    $user = User::firstWhere('email', RoleAndUserSeeder::emailFor($roleName));

    expect($user)->not->toBeNull()
        ->and($user->getRoleNames()->all())->toBe([$roleName->value])
        ->and($user->companies)->toHaveCount(4);
})->with(RoleName::cases());

it('uses readable generic emails', function () {
    expect(RoleAndUserSeeder::emailFor(RoleName::ApSupervisor))->toBe('ap.supervisor@ramcar.com')
        ->and(RoleAndUserSeeder::emailFor(RoleName::BuFpa))->toBe('bu.fpa@ramcar.com')
        ->and(RoleAndUserSeeder::emailFor(RoleName::ExcomChairman))->toBe('excom.chairman@ramcar.com')
        ->and(RoleAndUserSeeder::emailFor(RoleName::WarehouseRawMaterials))->toBe('warehouse.raw.materials@ramcar.com');
});

it('gives every user the shared test password', function () {
    User::all()->each(fn (User $user) => expect(Hash::check(RoleAndUserSeeder::PASSWORD, $user->password))->toBeTrue());
});

it('links the vendor login to the demo vendor', function () {
    expect(User::firstWhere('email', 'vendor@ramcar.com')->vendor->code)->toBe(RoleAndUserSeeder::DEMO_VENDOR_CODE);
});

it('truncates before seeding, so re-running gives the same data', function () {
    User::factory()->create(['email' => 'stray@example.com']);
    Role::findOrCreate('Stray Role');

    $this->seed(RoleAndUserSeeder::class);

    expect(User::count())->toBe(count(RoleName::cases()) + 1)
        ->and(User::where('email', 'stray@example.com')->exists())->toBeFalse()
        ->and(Role::where('name', 'Stray Role')->exists())->toBeFalse()
        ->and(User::query()->orderBy('id')->value('id'))->toBe(1);
});

it('rebuilds the approval matrix after truncating roles', function () {
    $this->seed(ApprovalMatrixSeeder::class);

    $this->seed(RoleAndUserSeeder::class);

    expect(ApprovalRule::findFor(RfpRequestType::Reimbursement, 500)->steps->first()->role->name)
        ->toBe(RoleName::LineManager->value);
});

it('refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    app(RoleAndUserSeeder::class)->run();
})->throws(RuntimeException::class);
