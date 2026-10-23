<?php

use App\Enums\RfpRequestType;
use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Resources\RfpApprovals\Pages\ListRfpApprovals;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Company;
use App\Models\Rfp\Item;
use App\Models\Rfp\Rfp;
use App\Models\User;
use App\Services\Rfp\ApprovalEngine;
use Database\Seeders\AccountGroupSeeder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\DemoMasterDataSeeder;
use Database\Seeders\Rfp\ApprovalMatrixSeeder;
use Database\Seeders\Rfp\DueTypeSeeder;
use Database\Seeders\Rfp\WorkflowSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        CompanySeeder::class, CurrencySeeder::class, AccountGroupSeeder::class, DemoMasterDataSeeder::class,
        RoleAndUserSeeder::class, DueTypeSeeder::class, ApprovalMatrixSeeder::class, WorkflowSeeder::class,
    ]);

    config(['rfp.auto_approve_matrix' => false]); // these walk the matrix themselves
    $this->engine = app(ApprovalEngine::class);
    $this->companyA = Company::firstWhere('code', '1000');
    $this->companyB = Company::firstWhere('code', '2000');
    $this->juan = User::factory()->create(['name' => 'Juan']);
});

function rfpSubmittedFor(Company $company, float $amount = 50_000): Rfp
{
    $requestor = User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::Requestor));
    $rfp = Rfp::factory()->for($requestor, 'requestor')->for($company)->create();
    Item::factory()->for($rfp)->create(['amount' => $amount]);
    $rfp->submit($requestor);

    return $rfp->refresh();
}

it('lets an approver act only in the companies they hold the role in', function () {
    $this->juan->syncCompanyRoles([['role' => RoleName::ApProcessor, 'companies' => [$this->companyA->id]]]);

    $inA = rfpSubmittedFor($this->companyA);
    $inB = rfpSubmittedFor($this->companyB);

    expect(Rfp::query()->awaiting($this->juan)->pluck('id')->all())->toBe([$inA->id]);

    $this->engine->approve($inA, $this->juan);
    expect($inA->refresh()->status)->toBe(RfpStatus::ForApproval)
        ->and(fn () => $this->engine->approve($inB, $this->juan))->toThrow(AuthorizationException::class);
});

it('lets one AP Processor serve two companies', function () {
    $this->juan->syncCompanyRoles([['role' => RoleName::ApProcessor, 'companies' => [$this->companyA->id, $this->companyB->id]]]);

    $inA = rfpSubmittedFor($this->companyA);
    $inB = rfpSubmittedFor($this->companyB);

    expect(Rfp::query()->awaiting($this->juan)->pluck('id')->sort()->values()->all())->toBe([$inA->id, $inB->id]);
});

it('routes by the role held in each company, even when it differs', function () {
    $this->juan->syncCompanyRoles([
        ['role' => RoleName::DepartmentHead, 'companies' => [$this->companyA->id]],
        ['role' => RoleName::BuFpa, 'companies' => [$this->companyB->id]],
    ]);
    $apProcessor = User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::ApProcessor));

    $inA = rfpSubmittedFor($this->companyA);
    $inB = rfpSubmittedFor($this->companyB);
    $this->engine->approve($inA, $apProcessor);
    $this->engine->approve($inB, $apProcessor);

    expect(Rfp::query()->awaiting($this->juan)->pluck('id')->all())->toBe([$inA->id]);

    $this->engine->approve($inA, $this->juan);
    $this->engine->approve($inB, User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::DepartmentHead)));

    expect($inA->refresh()->current_approver_role)->toBe(RoleName::BuFpa)
        ->and(Rfp::query()->awaiting($this->juan)->pluck('id')->all())->toBe([$inB->id]);
});

it('shows AP only the RFPs of companies they hold a role in', function () {
    $this->juan->syncCompanyRoles([['role' => RoleName::ApSupervisor, 'companies' => [$this->companyA->id]]]);

    $inA = rfpSubmittedFor($this->companyA);
    $inB = rfpSubmittedFor($this->companyB);

    $this->actingAs($this->juan);

    Livewire::test(ListRfpApprovals::class)
        ->set('activeTab', 'all')
        ->assertCanSeeTableRecords([$inA])
        ->assertCanNotSeeTableRecords([$inB]);
});

it('gives the screens of an approval role held in any company, and takes them back when none is left', function () {
    $this->juan->syncCompanyRoles([['role' => RoleName::ApProcessor, 'companies' => [$this->companyA->id]]]);
    $this->juan->assignRole(RoleName::Requestor->value);

    expect($this->juan->refresh()->getRoleNames()->sort()->values()->all())->toBe([RoleName::ApProcessor->value, RoleName::Requestor->value]);

    $this->juan->syncCompanyRoles([]);

    expect($this->juan->refresh()->getRoleNames()->all())->toBe([RoleName::Requestor->value]);
});

it('saves approval roles by company from the Users screen', function () {
    $this->actingAs(User::firstWhere('email', RoleAndUserSeeder::OWNER_EMAIL));

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Maria',
            'email' => 'maria@ramcar.com',
            'password' => 'TestPass123!',
            'company_roles' => [
                ['role' => RoleName::LineManager->value, 'companies' => [$this->companyA->id]],
                ['role' => RoleName::DepartmentHead->value, 'companies' => [$this->companyB->id]],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $maria = User::firstWhere('email', 'maria@ramcar.com');

    expect($maria->rolesIn($this->companyA->id))->toBe([RoleName::LineManager->value])
        ->and($maria->rolesIn($this->companyB->id))->toBe([RoleName::DepartmentHead->value])
        ->and($maria->hasRole(RoleName::DepartmentHead))->toBeTrue();

    Livewire::test(EditUser::class, ['record' => $maria->id])
        ->assertSchemaStateSet(fn (array $state): bool => count($state['company_roles']) === 2)
        ->fillForm(['company_roles' => [['role' => RoleName::LineManager->value, 'companies' => [$this->companyA->id, $this->companyB->id]]]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($maria->refresh()->rolesIn($this->companyB->id))->toBe([RoleName::LineManager->value])
        ->and($maria->hasRole(RoleName::DepartmentHead))->toBeFalse();
});

it('moves on when any one of the users holding a role approves', function () {
    $maria = User::factory()->create(['name' => 'Maria']);
    foreach ([$this->juan, $maria] as $lineManager) {
        $lineManager->syncCompanyRoles([['role' => RoleName::LineManager, 'companies' => [$this->companyA->id]]]);
    }
    $requestor = User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::Requestor));
    $rfp = Rfp::factory()->for($requestor, 'requestor')->for($this->companyA)->create(['request_type' => RfpRequestType::Reimbursement]);
    Item::factory()->for($rfp)->create(['amount' => 50_000]);
    $rfp->submit($requestor);
    $this->engine->approve($rfp, User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::ApProcessor)));

    expect(Rfp::query()->awaiting($this->juan)->pluck('id')->all())->toBe([$rfp->id])
        ->and(Rfp::query()->awaiting($maria)->pluck('id')->all())->toBe([$rfp->id]);

    $this->engine->approve($rfp, $maria);

    expect($rfp->refresh()->current_approver_role)->toBe(RoleName::DepartmentHead)
        ->and($rfp->histories()->latest('id')->first()->user_id)->toBe($maria->id)
        ->and(Rfp::query()->awaiting($this->juan)->exists())->toBeFalse()
        ->and(fn () => $this->engine->approve($rfp, $this->juan))->toThrow(AuthorizationException::class);
});
