<?php

use App\Enums\RfpApprovalStepState;
use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Resources\RfpApprovals\Pages\ViewRfpApproval;
use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Filament\Resources\Rfps\Pages\ViewRfp;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        CompanySeeder::class, CurrencySeeder::class, AccountGroupSeeder::class, DemoMasterDataSeeder::class,
        RoleAndUserSeeder::class, DueTypeSeeder::class, ApprovalMatrixSeeder::class, WorkflowSeeder::class,
    ]);

    $this->requestor = User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::Requestor));
    $this->apProcessor = User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::ApProcessor));
});

function rfpInApReview(array $attributes = []): Rfp
{
    $rfp = Rfp::factory()->for(test()->requestor, 'requestor')->for(Company::firstWhere('code', '1000'))->create($attributes);
    Item::factory()->for($rfp)->create(['amount' => 50_000]);
    $rfp->submit(test()->requestor);

    return $rfp->refresh();
}

it('notifies the AP Processors of the company when a request is submitted', function () {
    rfpInApReview();

    expect($this->apProcessor->notifications()->count())->toBe(1)
        ->and($this->apProcessor->notifications()->first()->data['title'])->toContain('is waiting for you');
});

it('endorses into the matrix, which is auto-approved for now, on to AP processing', function () {
    $rfp = rfpInApReview();

    $this->actingAs($this->apProcessor);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->assertActionVisible('endorse')
        ->callAction('endorse', ['remarks' => 'Documents complete'])
        ->assertHasNoActionErrors()
        ->assertRedirect();

    expect($rfp->refresh())
        ->status->toBe(RfpStatus::ForApProcessing)
        ->current_approver_role->toBe(RoleName::ApProcessor)
        ->and($rfp->approvalSteps()->where('kind', 'matrix')->pluck('state')->unique()->all())->toBe([RfpApprovalStepState::Approved])
        ->and($rfp->histories()->reorder('id')->pluck('action')->all())->toBe(['Submitted', 'Endorsed (AP Processor)', 'Matrix auto-approved'])
        ->and($rfp->histories()->where('action', 'Endorsed (AP Processor)')->value('remarks'))->toBe('Documents complete');
});

it('endorses into the matrix approvers once matrix approval is live', function () {
    config(['rfp.auto_approve_matrix' => false]);
    $rfp = rfpInApReview();

    $this->actingAs($this->apProcessor);
    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])->callAction('endorse');

    expect($rfp->refresh())->status->toBe(RfpStatus::ForApproval)->current_approver_role->toBe(RoleName::DepartmentHead);
});

it('sends a pre-approved request past the matrix', function () {
    config(['rfp.auto_approve_matrix' => false]);
    $rfp = rfpInApReview(['is_pre_approved' => true, 'pre_approval_documents' => ['rfp-attachments/approval.pdf']]);

    $this->actingAs($this->apProcessor);
    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])->callAction('endorse');

    expect($rfp->refresh()->status)->toBe(RfpStatus::ForApProcessing);
});

it('returns the request to the requestor with required remarks, and shows them on the request', function () {
    $rfp = rfpInApReview();

    $this->actingAs($this->apProcessor);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->callAction('returnForRevision', ['remarks' => ''])
        ->assertHasActionErrors(['remarks' => 'required']);

    expect($rfp->refresh()->status)->toBe(RfpStatus::ForApReview);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->callAction('returnForRevision', ['remarks' => 'Attach the official receipt'])
        ->assertHasNoActionErrors();

    expect($rfp->refresh())->status->toBe(RfpStatus::ForRevision)
        ->and($this->requestor->notifications()->first()->data['title'])->toBe("{$rfp->rfp_number} was returned for revision");

    $this->actingAs($this->requestor);

    Livewire::test(ViewRfp::class, ['record' => $rfp->getRouteKey()])
        ->assertSee('Returned for revision')
        ->assertSee('Attach the official receipt')
        ->assertActionVisible('submit');
});

it('brings a resubmitted request back to AP review', function () {
    $rfp = rfpInApReview();
    app(ApprovalEngine::class)->returnToRequestor($rfp, $this->apProcessor, 'Fix the date');

    $rfp->submit($this->requestor);

    expect($rfp->refresh())->status->toBe(RfpStatus::ForApReview)->current_approver_role->toBe(RoleName::ApProcessor);
});

it('offers no reject or cancel at AP review', function () {
    $rfp = rfpInApReview();

    $this->actingAs($this->apProcessor);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->assertActionVisible('endorse')
        ->assertActionDoesNotExist('reject')
        ->assertActionHidden('cancel');
});

it('hides the review actions from anyone who is not the AP Processor', function (User $user) {
    $rfp = rfpInApReview();

    $this->actingAs($user);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->assertActionHidden('endorse')
        ->assertActionHidden('returnForRevision');
})->with([
    'AP Supervisor' => fn () => User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::ApSupervisor)),
    'Admin' => fn () => User::firstWhere('email', RoleAndUserSeeder::OWNER_EMAIL),
]);

it('keeps an AP Processor of another company out of the request', function () {
    $rfp = rfpInApReview();
    $otherCompanyAp = User::factory()->create();
    $otherCompanyAp->syncCompanyRoles([['role' => RoleName::ApProcessor, 'companies' => [Company::firstWhere('code', '2000')->id]]]);

    $this->actingAs($otherCompanyAp)
        ->get(RfpApprovalResource::getUrl('view', ['record' => $rfp]))
        ->assertNotFound();
});

it('hides the review actions once the request has moved on', function () {
    $rfp = rfpInApReview();
    app(ApprovalEngine::class)->approve($rfp, $this->apProcessor);

    $this->actingAs($this->apProcessor);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->assertActionHidden('endorse');
});
