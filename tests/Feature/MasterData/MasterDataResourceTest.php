<?php

use App\Enums\RfpRequestType;
use App\Enums\RoleName;
use App\Filament\Clusters\MasterData\Resources\Companies\Pages\ManageCompanies;
use App\Filament\Clusters\RfpSettings\Resources\ApprovalRules\Pages\CreateApprovalRule;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Company;
use App\Models\Rfp\ApprovalRule;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleAndUserSeeder;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndUserSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(RoleName::Admin));
});

it('rejects a duplicate company code', function () {
    Company::factory()->create(['code' => '1000']);

    Livewire::test(ManageCompanies::class)
        ->callAction('create', ['code' => '1000', 'name' => 'Duplicate Co.'])
        ->assertHasFormErrors(['code' => 'unique']);
});

it('creates an approval tier with its approver steps', function () {
    $undoRepeaterFake = Repeater::fake();

    Livewire::test(CreateApprovalRule::class)
        ->fillForm([
            'request_type' => RfpRequestType::Standard,
            'max_amount' => 2_000_000,
            'steps' => [
                ['step_order' => 1, 'role_id' => Role::findByName(RoleName::BuHead->value)->id],
                ['step_order' => 2, 'role_id' => Role::findByName(RoleName::FpaHead->value)->id],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $undoRepeaterFake();

    $rule = ApprovalRule::sole();

    expect($rule->request_type)->toBe(RfpRequestType::Standard)
        ->and($rule->steps->pluck('role.name')->all())->toBe([RoleName::BuHead->value, RoleName::FpaHead->value]);
});

it('rejects the same role twice in one tier', function () {
    $undoRepeaterFake = Repeater::fake();
    $roleId = Role::findByName(RoleName::LineManager->value)->id;

    Livewire::test(CreateApprovalRule::class)
        ->fillForm([
            'request_type' => RfpRequestType::Reimbursement,
            'steps' => [
                ['step_order' => 1, 'role_id' => $roleId],
                ['step_order' => 2, 'role_id' => $roleId],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['steps.0.role_id', 'steps.1.role_id']);

    $undoRepeaterFake();

    expect(ApprovalRule::count())->toBe(0);
});

it('does not allow deleting or renaming a system role', function () {
    $role = Role::findByName(RoleName::ApSupervisor->value);

    Livewire::test(EditRole::class, ['record' => $role->getKey()])
        ->assertActionHidden(DeleteAction::class)
        ->assertFormFieldDisabled('name');
});

it('requires a vendor for Vendor logins', function () {
    $vendorRoleId = Role::findByName(RoleName::Vendor->value)->id;

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Supplier Login',
            'email' => 'supplier@example.com',
            'password' => 'password123',
            'roles' => [$vendorRoleId],
        ])
        ->call('create')
        ->assertHasFormErrors(['vendors' => 'required']);

    $vendor = Vendor::factory()->create();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Supplier Login',
            'email' => 'supplier@example.com',
            'password' => 'password123',
            'roles' => [$vendorRoleId],
            'vendors' => [$vendor->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::firstWhere('email', 'supplier@example.com');

    expect($user->vendor_id)->toBe($vendor->id)
        ->and($user->hasRole(RoleName::Vendor))->toBeTrue()
        ->and($user->vendors()->pluck('vendors.id')->all())->toBe([$vendor->id]);
});
