<?php

use App\Enums\RfpApprovalStepState;
use App\Enums\RfpRequestType;
use App\Enums\RfpStageKind;
use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Clusters\RfpSettings\Pages\ApprovalWorkflow;
use App\Models\Company;
use App\Models\Rfp\ApprovalRule;
use App\Models\Rfp\Item;
use App\Models\Rfp\Rfp;
use App\Models\Rfp\WorkflowStage;
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
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        CompanySeeder::class, CurrencySeeder::class, AccountGroupSeeder::class, DemoMasterDataSeeder::class,
        RoleAndUserSeeder::class, DueTypeSeeder::class, ApprovalMatrixSeeder::class, WorkflowSeeder::class,
    ]);

    config(['rfp.auto_approve_matrix' => false]); // these walk the matrix themselves
    $this->engine = app(ApprovalEngine::class);
    $this->requestor = userWithRole(RoleName::Requestor);
});

function userWithRole(RoleName $role): User
{
    return User::firstWhere('email', RoleAndUserSeeder::emailFor($role));
}

/**
 * A submitted standard RFP; up to PHP 1M its matrix tier is Department Head → BU FP&A.
 */
function submittedRfp(float $amount = 50_000, array $attributes = []): Rfp
{
    $rfp = Rfp::factory()
        ->for(test()->requestor, 'requestor')
        ->for(Company::firstWhere('code', '1000'))
        ->create($attributes);

    Item::factory()->for($rfp)->create(['amount' => $amount]);
    $rfp->submit(test()->requestor);

    return $rfp->refresh();
}

/**
 * Approve as whoever the RFP waits for, once per role given.
 */
function approveAs(Rfp $rfp, RoleName ...$roles): void
{
    foreach ($roles as $role) {
        test()->engine->approve($rfp, userWithRole($role));
    }
}

/**
 * @return list<string>
 */
function routeOf(Rfp $rfp): array
{
    return $rfp->approvalSteps()->where('round', $rfp->approval_round)->get()
        ->map(fn ($step): string => "{$step->label}:{$step->state->value}")
        ->all();
}

it('copies the workflow onto the RFP on submit, with the matrix tier expanded', function () {
    $rfp = submittedRfp();

    expect($rfp)
        ->status->toBe(RfpStatus::ForApReview)
        ->current_approver_role->toBe(RoleName::ApProcessor)
        ->and(routeOf($rfp))->toBe([
            'AP Processor:active', 'Department Head:pending', 'BU FP&A:pending',
            'AP Processor:pending', 'AP Supervisor:pending', 'AP Manager:pending', 'Treasury:pending',
        ]);
});

it('walks the full route to completed, one stage at a time', function () {
    $rfp = submittedRfp();

    approveAs($rfp, RoleName::ApProcessor, RoleName::DepartmentHead);
    expect($rfp->refresh())->status->toBe(RfpStatus::ForApproval)->current_approver_role->toBe(RoleName::BuFpa);

    approveAs($rfp, RoleName::BuFpa, RoleName::ApProcessor, RoleName::ApSupervisor);
    expect($rfp->refresh())->status->toBe(RfpStatus::ForAccountingApproval)->current_approver_role->toBe(RoleName::ApManager);

    approveAs($rfp, RoleName::ApManager, RoleName::Treasury);
    expect($rfp->refresh())->status->toBe(RfpStatus::Completed)->current_approver_role->toBeNull()
        ->and($rfp->histories()->latest('id')->first()->action)->toBe('Approved (Treasury)');
});

it('only lets the role the RFP waits for approve', function () {
    $rfp = submittedRfp();

    $this->engine->approve($rfp, userWithRole(RoleName::ApSupervisor));
})->throws(AuthorizationException::class);

it('lets one person holding two roles approve each stage separately', function () {
    $rfp = submittedRfp();
    approveAs($rfp, RoleName::ApProcessor, RoleName::DepartmentHead, RoleName::BuFpa, RoleName::ApProcessor);

    $supervisorAndManager = userWithRole(RoleName::ApSupervisor);
    $supervisorAndManager->syncCompanyRoles([
        ...$supervisorAndManager->companyRoleRows(),
        ['role' => RoleName::ApManager, 'companies' => [$rfp->company_id]],
    ]);

    $this->engine->approve($rfp, $supervisorAndManager);
    expect($rfp->refresh()->current_approver_role)->toBe(RoleName::ApManager);

    $this->engine->approve($rfp, $supervisorAndManager);
    expect($rfp->refresh()->status)->toBe(RfpStatus::ForTreasury);
});

it('waits for every parallel matrix role before moving on', function () {
    ApprovalRule::findFor(RfpRequestType::Standard, 50_000)
        ->steps()->update(['step_order' => 1]);

    $rfp = submittedRfp();
    approveAs($rfp, RoleName::ApProcessor);

    expect(Rfp::query()->awaiting(userWithRole(RoleName::BuFpa))->pluck('id')->all())->toBe([$rfp->id])
        ->and(Rfp::query()->awaiting(userWithRole(RoleName::DepartmentHead))->pluck('id')->all())->toBe([$rfp->id]);

    approveAs($rfp, RoleName::BuFpa);
    expect($rfp->refresh()->status)->toBe(RfpStatus::ForApproval);

    approveAs($rfp, RoleName::DepartmentHead);
    expect($rfp->refresh()->status)->toBe(RfpStatus::ForApProcessing);
});

it('keeps the route an RFP was submitted with when the workflow changes', function () {
    $rfp = submittedRfp();

    WorkflowStage::query()->where('label', 'AP Manager')->delete();
    WorkflowStage::query()->where('label', 'Treasury')->update(['position' => 5]);

    approveAs($rfp, RoleName::ApProcessor, RoleName::DepartmentHead, RoleName::BuFpa, RoleName::ApProcessor, RoleName::ApSupervisor);

    expect($rfp->refresh()->current_approver_role)->toBe(RoleName::ApManager);
});

it('skips the matrix for a pre-approved request', function () {
    $rfp = submittedRfp(attributes: ['is_pre_approved' => true, 'pre_approval_documents' => ['rfp-attachments/approval.pdf']]);
    approveAs($rfp, RoleName::ApProcessor);

    expect($rfp->refresh()->status)->toBe(RfpStatus::ForApProcessing)
        ->and(routeOf($rfp)[1])->toBe('Pre-approved (no matrix):skipped');
});

it('returns to the requestor, and on resubmission skips AP review and the matrix it kept', function () {
    $rfp = submittedRfp();
    approveAs($rfp, RoleName::ApProcessor, RoleName::DepartmentHead, RoleName::BuFpa);

    $this->engine->returnToRequestor($rfp, userWithRole(RoleName::ApProcessor), 'Wrong invoice date', keepMatrixApproval: true);
    expect($rfp->refresh())->status->toBe(RfpStatus::ForRevision)->currentApproverLabel()->toBe('Requestor');

    $rfp->submit($this->requestor);

    expect($rfp->refresh())
        ->status->toBe(RfpStatus::ForApProcessing)
        ->approval_round->toBe(2)
        ->and(routeOf($rfp))->toBe([
            'AP Processor:skipped', 'Matrix approved in an earlier round:skipped',
            'AP Processor:active', 'AP Supervisor:pending', 'AP Manager:pending', 'Treasury:pending',
        ]);
});

it('runs the matrix again on resubmission when the amount changed or AP did not keep it', function (bool $keepMatrixApproval, float $newAmount) {
    $rfp = submittedRfp();
    approveAs($rfp, RoleName::ApProcessor, RoleName::DepartmentHead, RoleName::BuFpa);
    $this->engine->returnToRequestor($rfp, userWithRole(RoleName::ApProcessor), 'Please fix', $keepMatrixApproval);

    $rfp->items()->first()->update(['amount' => $newAmount]);
    $rfp->submit($this->requestor);

    expect($rfp->refresh())->status->toBe(RfpStatus::ForApproval)->current_approver_role->toBe(RoleName::DepartmentHead);
})->with([
    'amount changed' => [true, 60_000],
    'matrix not kept' => [false, 50_000],
]);

it('sends the RFP back to AP processing from AP Supervisor, then through accounting again', function () {
    $rfp = submittedRfp();
    approveAs($rfp, RoleName::ApProcessor, RoleName::DepartmentHead, RoleName::BuFpa, RoleName::ApProcessor);

    $this->engine->returnToStage($rfp, userWithRole(RoleName::ApSupervisor), 'Tax code is wrong');
    expect($rfp->refresh())->status->toBe(RfpStatus::ForApProcessing)->current_approver_role->toBe(RoleName::ApProcessor)
        ->and($rfp->histories()->latest('id')->first())->action->toBe('Returned to AP Processor')->remarks->toBe('Tax code is wrong');

    approveAs($rfp, RoleName::ApProcessor);
    expect($rfp->refresh()->current_approver_role)->toBe(RoleName::ApSupervisor);
});

it('requires remarks to send back, reject or cancel', function () {
    $rfp = submittedRfp();

    $this->engine->returnToRequestor($rfp, userWithRole(RoleName::ApProcessor), ' ');
})->throws(ValidationException::class);

it('lets matrix approvers reject but not AP Supervisor', function () {
    $rfp = submittedRfp();
    approveAs($rfp, RoleName::ApProcessor);

    $this->engine->reject($rfp, userWithRole(RoleName::DepartmentHead), 'Not budgeted');
    expect($rfp->refresh())->status->toBe(RfpStatus::Rejected)
        ->and($rfp->approvalSteps()->where('state', RfpApprovalStepState::Superseded)->count())->toBe(5);

    $other = submittedRfp();
    approveAs($other, RoleName::ApProcessor, RoleName::DepartmentHead, RoleName::BuFpa, RoleName::ApProcessor);

    expect(fn () => $this->engine->reject($other, userWithRole(RoleName::ApSupervisor), 'No'))->toThrow(AuthorizationException::class);
});

it('refuses to submit when no workflow is set up', function () {
    WorkflowStage::query()->delete();

    submittedRfp();
})->throws(ValidationException::class, 'No approval workflow is set up');

it('previews the route from the workflow', function () {
    WorkflowStage::query()->where('label', 'AP Manager')->update(['label' => 'Controller']);

    expect($this->engine->preview(RfpRequestType::Standard, '50000', false))
        ->toBe(['AP Processor', 'Department Head', 'BU FP&A', 'AP Processor', 'AP Supervisor', 'Controller', 'Treasury']);
});

it('rejects workflows that break the required controls', function (Closure $change, string $problem) {
    $stages = WorkflowStage::ordered()->map(fn (WorkflowStage $stage): array => [
        'kind' => $stage->kind, 'role' => $stage->role, 'return_to_position' => $stage->return_to_position,
    ])->all();

    expect(WorkflowStage::problemsWith($change($stages)))->toContain($problem);
})->with([
    'Treasury not last' => [fn (array $stages): array => [...array_slice($stages, 0, 4), $stages[5], $stages[4]], 'The workflow must end with exactly one payment release stage.'],
    'no AP processing' => [fn (array $stages): array => [...array_slice($stages, 0, 2), ...array_slice($stages, 3)], 'The workflow must have exactly one AP processing stage.'],
    'two matrices' => [fn (array $stages): array => [$stages[0], $stages[1], $stages[1], ...array_slice($stages, 2)], 'The workflow can have only one approval matrix stage.'],
    'not AP first' => [fn (array $stages): array => array_slice($stages, 1), 'The first stage must be an AP review (AP Processor, AP Supervisor or AP Manager).'],
    'return forward' => [fn (array $stages): array => array_replace($stages, [2 => [...$stages[2], 'return_to_position' => 5]]), 'Stage 3 can only return to an earlier stage that is not the approval matrix.'],
    'return to matrix' => [fn (array $stages): array => array_replace($stages, [3 => [...$stages[3], 'return_to_position' => 2]]), 'Stage 4 can only return to an earlier stage that is not the approval matrix.'],
    'approval without role' => [fn (array $stages): array => array_replace($stages, [3 => [...$stages[3], 'role' => null]]), 'Stage 4 needs a role.'],
]);

it('accepts the default workflow', function () {
    $stages = WorkflowStage::ordered()->map(fn (WorkflowStage $stage): array => [
        'kind' => $stage->kind, 'role' => $stage->role, 'return_to_position' => $stage->return_to_position,
    ])->all();

    expect(WorkflowStage::problemsWith($stages))->toBe([]);
});

it('lets Admins add a stage on the Approval Workflow page', function () {
    $this->actingAs(userWithRole(RoleName::Admin));

    $component = Livewire::test(ApprovalWorkflow::class);
    $stages = $component->get('data.stages');
    $treasury = array_pop($stages);

    $component
        ->set('data.stages', [...$stages, [
            'kind' => RfpStageKind::Approval->value, 'role' => RoleName::Controller->value, 'label' => 'Controller',
            'status' => RfpStatus::ForAccountingApproval->value, 'can_return_to_requestor' => false,
            'return_to_position' => 3, 'can_reject' => true, 'can_cancel' => false,
        ], $treasury])
        ->call('save')
        ->assertHasNoErrors()
        ->assertNotified('Approval workflow saved');

    expect(WorkflowStage::ordered()->pluck('label')->all())
        ->toBe(['AP Processor', 'Approval matrix', 'AP Processor', 'AP Supervisor', 'AP Manager', 'Controller', 'Treasury']);
});

it('keeps the saved workflow when the change breaks a control', function () {
    $this->actingAs(userWithRole(RoleName::Admin));

    $component = Livewire::test(ApprovalWorkflow::class);
    $stages = $component->get('data.stages');
    array_pop($stages);

    $component->set('data.stages', $stages)
        ->call('save')
        ->assertHasErrors(['data.stages']);

    expect(WorkflowStage::count())->toBe(6);
});

it('keeps non-Admins off the Approval Workflow page', function () {
    $this->actingAs(userWithRole(RoleName::ApManager))
        ->get(ApprovalWorkflow::getUrl())
        ->assertForbidden();
});

it('draws the flow with who holds each role in the preview company', function () {
    $this->actingAs(userWithRole(RoleName::Admin));
    $companyB = Company::firstWhere('code', '2000');
    userWithRole(RoleName::BuFpa)->syncCompanyRoles([['role' => RoleName::BuFpa, 'companies' => [Company::firstWhere('code', '1000')->id]]]);

    Livewire::test(ApprovalWorkflow::class)
        ->assertSeeInOrder(['Requestor', 'AP Processor', 'Department Head', 'BU FP&amp;A', 'Treasury', 'Paid'], escape: false)
        ->assertSee('Demo BU FP&amp;A', escape: false)
        ->set('previewCompanyId', $companyB->id)
        ->assertSee('Nobody in this company')
        ->set('previewAmount', '2000000')
        ->assertSee('FP&amp;A Head', escape: false);
});

it('redraws the flow from unsaved edits', function () {
    $this->actingAs(userWithRole(RoleName::Admin));

    $component = Livewire::test(ApprovalWorkflow::class);
    $stages = $component->get('data.stages');
    $key = array_keys($stages)[4];

    $component->set("data.stages.{$key}.label", 'Finance Controller')
        ->assertSee('Finance Controller');

    expect(WorkflowStage::where('label', 'Finance Controller')->exists())->toBeFalse();
});
