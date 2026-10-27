<?php

use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Resources\RfpApprovals\Pages\ListRfpApprovals;
use App\Filament\Resources\RfpApprovals\Pages\ViewRfpApproval;
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
    $this->treasury = User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::Treasury));
});

function rfpWithTreasury(): Rfp
{
    $rfp = Rfp::factory()->for(test()->requestor, 'requestor')->for(Company::firstWhere('code', '1000'))->create();
    Item::factory()->for($rfp)->create(['amount' => 5000]);
    $rfp->submit(test()->requestor);

    $engine = app(ApprovalEngine::class);
    foreach ([RoleName::ApProcessor, RoleName::ApProcessor, RoleName::ApSupervisor, RoleName::ApManager] as $role) {
        $engine->approve($rfp, User::firstWhere('email', RoleAndUserSeeder::emailFor($role)));
    }

    return $rfp->refresh();
}

it('releases the payment, which completes the request and tells the requestor', function () {
    $rfp = rfpWithTreasury();
    expect($rfp->status)->toBe(RfpStatus::ForTreasury);

    $this->actingAs($this->treasury);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->callAction('releasePayment', ['is_ids' => false, 'remarks' => 'Released via BDO'])
        ->assertHasNoActionErrors()
        ->assertNotified("Payment released for {$rfp->rfp_number}");

    expect($rfp->refresh())
        ->status->toBe(RfpStatus::Completed)
        ->current_approver_role->toBeNull()
        ->is_ids->toBeFalse()
        ->and($rfp->histories()->latest('id')->first())->action->toBe('Payment released (Treasury)')->remarks->toBe('Released via BDO')
        ->and($this->requestor->notifications()->latest()->first()->data)
        ->title->toBe("{$rfp->rfp_number} is completed")
        ->body->toBe("Payment released (Treasury) by {$this->treasury->name}.");
});

it('lets Treasury open RFP Approvals and see what waits for them', function () {
    $rfp = rfpWithTreasury();

    $this->actingAs($this->treasury)
        ->get(ViewRfpApproval::getUrl(['record' => $rfp]))
        ->assertOk();

    Livewire::test(ListRfpApprovals::class)
        ->set('activeTab', 'for_treasury')
        ->assertCanSeeTableRecords([$rfp]);
});

it('offers the release only to Treasury, only at the Treasury step', function () {
    $rfp = rfpWithTreasury();

    $this->actingAs(User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::ApManager)));
    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->assertActionHidden('releasePayment');

    app(ApprovalEngine::class)->approve($rfp, $this->treasury);

    $this->actingAs($this->treasury);
    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->assertActionHidden('releasePayment');
});

it('offers Treasury no return or cancel', function () {
    $rfp = rfpWithTreasury();
    $this->actingAs($this->treasury);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->assertActionVisible('releasePayment')
        ->assertActionHidden('cancel')
        ->assertActionHidden('returnToStage')
        ->assertActionHidden('returnForRevision');
});
