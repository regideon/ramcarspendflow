<?php

use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Resources\RfpApprovals\Pages\ListRfpApprovals;
use App\Filament\Resources\RfpApprovals\Pages\ViewRfpApproval;
use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Filament\Resources\Rfps\Pages\ListRfps;
use App\Models\Company;
use App\Models\Rfp\Item;
use App\Models\Rfp\Rfp;
use App\Models\User;
use Database\Seeders\AccountGroupSeeder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\DemoMasterDataSeeder;
use Database\Seeders\Rfp\DueTypeSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([CompanySeeder::class, CurrencySeeder::class, AccountGroupSeeder::class, DemoMasterDataSeeder::class, RoleAndUserSeeder::class, DueTypeSeeder::class]);

    $this->company = Company::firstWhere('code', '1000');
    $this->requestor = User::firstWhere('email', 'requestor@ramcar.com');
    $this->apProcessor = User::firstWhere('email', 'ap.processor@ramcar.com');
    $this->lineManager = User::firstWhere('email', 'line.manager@ramcar.com');
    $this->departmentHead = User::firstWhere('email', 'department.head@ramcar.com');
});

function approvalRfp(RfpStatus $status, ?RoleName $role = null, ?User $approver = null): Rfp
{
    return Rfp::factory()->for(test()->requestor, 'requestor')->for(test()->company)->create()
        ->forceFill(['status' => $status, 'current_approver_role' => $role, 'current_approver_id' => $approver?->id]);
}

function saved(Rfp $rfp): Rfp
{
    $rfp->saveQuietly();

    return $rfp;
}

it('is only for Admin, AP Processor, AP Supervisor and AP Manager', function (string $email, int $status) {
    $this->actingAs(User::firstWhere('email', $email))->get('/hub/rfp-approvals')->assertStatus($status);
})->with([
    'Admin' => [RoleAndUserSeeder::OWNER_EMAIL, 200],
    'AP Processor' => ['ap.processor@ramcar.com', 200],
    'AP Supervisor' => ['ap.supervisor@ramcar.com', 200],
    'AP Manager' => ['ap.manager@ramcar.com', 200],
    'Treasury' => ['treasury@ramcar.com', 403],
    'Line Manager' => ['line.manager@ramcar.com', 403],
    'Department Head' => ['department.head@ramcar.com', 403],
    'Requestor' => ['requestor@ramcar.com', 403],
]);

it('shows Accounting every submitted RFP, but no drafts', function () {
    $submitted = saved(approvalRfp(RfpStatus::ForApproval, RoleName::LineManager));
    $completed = saved(approvalRfp(RfpStatus::Completed));
    $draft = saved(approvalRfp(RfpStatus::Draft));

    $this->actingAs($this->apProcessor);

    Livewire::test(ListRfpApprovals::class, ['activeTab' => 'all'])
        ->assertCanSeeTableRecords([$submitted, $completed])
        ->assertCanNotSeeTableRecords([$draft]);
});

it('keeps the queue rules for business approvers (what waits for them or what they acted on)', function () {
    $assignedToMe = saved(approvalRfp(RfpStatus::ForApproval, RoleName::LineManager, $this->lineManager));
    $anyLineManager = saved(approvalRfp(RfpStatus::ForApproval, RoleName::LineManager));
    $assignedToOther = saved(approvalRfp(RfpStatus::ForApproval, RoleName::LineManager, User::factory()->create()));
    $otherRole = saved(approvalRfp(RfpStatus::ForApproval, RoleName::DepartmentHead));
    $iApproved = saved(approvalRfp(RfpStatus::Completed));
    $iApproved->histories()->create(['user_id' => $this->lineManager->id, 'action' => 'Approved']);

    // Business approvers don't use the RFP Approvals page; the queue rules are kept for their approval screen
    expect(Rfp::query()->awaiting($this->lineManager)->pluck('id')->sort()->values()->all())->toBe([$assignedToMe->id, $anyLineManager->id])
        ->and(Rfp::query()->inApprovalsOf($this->lineManager)->pluck('id')->sort()->values()->all())->toBe([$assignedToMe->id, $anyLineManager->id, $iApproved->id]);
});

it('counts what waits for the signed-in AP user in the menu badge', function () {
    saved(approvalRfp(RfpStatus::ForApProcessing, RoleName::ApProcessor));
    saved(approvalRfp(RfpStatus::ForApProcessing, RoleName::ApProcessor, $this->apProcessor));
    saved(approvalRfp(RfpStatus::ForAccountingApproval, RoleName::ApSupervisor));

    $this->actingAs($this->apProcessor);

    expect(RfpApprovalResource::getNavigationBadge())->toBe('2');
});

it('splits the accounting queue like legacy (AP, AS, AM)', function () {
    $withSupervisor = saved(approvalRfp(RfpStatus::ForAccountingApproval, RoleName::ApSupervisor));
    $withManager = saved(approvalRfp(RfpStatus::ForAccountingApproval, RoleName::ApManager));

    $this->actingAs($this->apProcessor);

    Livewire::test(ListRfpApprovals::class, ['activeTab' => 'with_ap_supervisor'])
        ->assertCanSeeTableRecords([$withSupervisor])
        ->assertCanNotSeeTableRecords([$withManager])
        ->set('activeTab', 'with_ap_manager')
        ->assertCanSeeTableRecords([$withManager])
        ->assertCanNotSeeTableRecords([$withSupervisor]);
});

it('puts a submitted RFP in the AP Processor queue', function () {
    $rfp = Rfp::factory()->for($this->requestor, 'requestor')->for($this->company)->create();
    $rfp->items()->create(Item::factory()->make(['rfp_id' => $rfp->id])->only(['rfp_subcategory_id', 'invoice_number', 'invoice_date', 'description', 'currency_id', 'amount', 'attachments']));
    $rfp->submit($this->requestor);

    expect($rfp->refresh())
        ->current_approver_role->toBe(RoleName::ApProcessor)
        ->currentApproverLabel()->toBe('AP Processor')
        ->and(Rfp::query()->awaiting($this->apProcessor)->pluck('id')->all())->toBe([$rfp->id]);
});

it('opens the RFP with who it is with', function () {
    $rfp = saved(approvalRfp(RfpStatus::ForApproval, RoleName::LineManager, $this->lineManager));

    $this->actingAs($this->apProcessor);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getKey()])
        ->assertOk()
        ->assertSee("Currently with: {$this->lineManager->name}");
});

it('keeps My Requests to the user\'s own RFPs, even for Admins', function () {
    $theirs = saved(approvalRfp(RfpStatus::ForApproval, RoleName::LineManager));
    $admin = User::firstWhere('email', RoleAndUserSeeder::OWNER_EMAIL);
    $mine = Rfp::factory()->for($admin, 'requestor')->for($this->company)->create();

    $this->actingAs($admin);

    Livewire::test(ListRfps::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});
