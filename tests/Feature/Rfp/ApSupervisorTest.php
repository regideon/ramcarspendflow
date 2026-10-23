<?php

use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Resources\RfpApprovals\Pages\ViewRfpApproval;
use App\Models\Company;
use App\Models\Rfp\Item;
use App\Models\Rfp\Rfp;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\WithholdingTaxCode;
use App\Services\Rfp\ApprovalEngine;
use App\Services\Rfp\RfpTaxProcessing;
use Database\Seeders\AccountGroupSeeder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\DemoMasterDataSeeder;
use Database\Seeders\Rfp\ApprovalMatrixSeeder;
use Database\Seeders\Rfp\DueTypeSeeder;
use Database\Seeders\Rfp\WorkflowSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\TaxCodeSeeder;
use Database\Seeders\WithholdingTaxSeeder;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        CompanySeeder::class, CurrencySeeder::class, AccountGroupSeeder::class, DemoMasterDataSeeder::class,
        RoleAndUserSeeder::class, DueTypeSeeder::class, ApprovalMatrixSeeder::class, WorkflowSeeder::class,
        TaxCodeSeeder::class, WithholdingTaxSeeder::class,
    ]);

    $this->requestor = User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::Requestor));
    $this->apProcessor = User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::ApProcessor));
    $this->supervisor = User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::ApSupervisor));
    $this->inputTax = TaxCode::firstWhere('code', 'I2'); // 12%
    $this->withholding = WithholdingTaxCode::firstWhere('code', '04'); // 2%

    $this->undoRepeaterFake = Repeater::fake();
});

afterEach(function () {
    ($this->undoRepeaterFake)();
});

/**
 * An RFP the AP Processor has coded (I2, 2%), now with the AP Supervisor.
 */
function rfpWithSupervisor(): Rfp
{
    $rfp = Rfp::factory()->for(test()->requestor, 'requestor')->for(Company::firstWhere('code', '1000'))->create();
    Item::factory()->for($rfp)->create(['amount' => '11200.00', 'invoice_number' => 'INV-1']);
    $rfp->submit(test()->requestor);
    app(ApprovalEngine::class)->approve($rfp, test()->apProcessor, verb: 'Endorsed');

    app(RfpTaxProcessing::class)->process($rfp, test()->apProcessor, $rfp->items->map(fn (Item $item): array => [
        'id' => $item->id,
        'amount' => (string) $item->amount,
        'tax_code_id' => test()->inputTax->id,
        'withholding_tax_code_id' => test()->withholding->id,
    ])->all());

    return $rfp->refresh();
}

it('approves the codes the AP Processor booked as they are, and sends the request to the AP Manager', function () {
    $rfp = rfpWithSupervisor();
    $this->actingAs($this->supervisor);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->assertActionVisible('supervisorApprove')
        ->callAction('supervisorApprove', ['remarks' => 'Checked'])
        ->assertHasNoActionErrors();

    expect($rfp->refresh())
        ->status->toBe(RfpStatus::ForAccountingApproval)
        ->current_approver_role->toBe(RoleName::ApManager)
        ->and($rfp->histories()->latest('id')->first())->action->toBe('Approved (AP Supervisor)')->remarks->toBe('Checked')
        ->and($rfp->items()->first()->tax_code_id)->toBe($this->inputTax->id);
});

it('lets the AP Supervisor correct a tax code before approving', function () {
    $rfp = rfpWithSupervisor();
    $fivePercent = WithholdingTaxCode::firstWhere('rate', 5);
    $this->actingAs($this->supervisor);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->callAction('supervisorApprove', ['lines' => [[
            'id' => $rfp->items()->first()->id,
            'amount' => '11200.00',
            'tax_code_id' => $this->inputTax->id,
            'withholding_tax_code_id' => $fivePercent->id,
        ]]])
        ->assertHasNoActionErrors();

    expect($rfp->items()->first())
        ->withholding_tax_code_id->toBe($fivePercent->id)
        ->withholding_tax_amount->toBe('500.00')
        ->net_of_withholding_tax_amount->toBe('10700.00');
});

it('returns the request to the AP Processor with remarks, keeping the booked codes', function () {
    $rfp = rfpWithSupervisor();
    $this->actingAs($this->supervisor);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->assertActionVisible('returnToStage')
        ->callAction('returnToStage', ['remarks' => ''])
        ->assertHasActionErrors(['remarks' => 'required']);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->callAction('returnToStage', ['remarks' => 'Use I3, this is a service'])
        ->assertHasNoActionErrors()
        ->assertNotified("{$rfp->rfp_number} returned to AP Processor");

    expect($rfp->refresh())
        ->status->toBe(RfpStatus::ForApProcessing)
        ->current_approver_role->toBe(RoleName::ApProcessor)
        ->and($rfp->items()->first()->tax_code_id)->toBe($this->inputTax->id)
        ->and($this->apProcessor->notifications()->latest()->first()->data['title'])->toBe("{$rfp->rfp_number} is waiting for you");

    $this->actingAs($this->apProcessor);
    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->callAction('processTaxCodes')
        ->assertHasNoActionErrors();

    expect($rfp->refresh()->current_approver_role)->toBe(RoleName::ApSupervisor);
});

it('offers the AP Supervisor no cancel, reject or return to the requestor', function () {
    $rfp = rfpWithSupervisor();
    $this->actingAs($this->supervisor);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->assertActionHidden('cancel')
        ->assertActionHidden('returnForRevision')
        ->assertActionHidden('processTaxCodes');
});

it('shows the supervisor actions to nobody else', function (RoleName $role) {
    $rfp = rfpWithSupervisor();
    $this->actingAs(User::firstWhere('email', RoleAndUserSeeder::emailFor($role)));

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->assertActionHidden('supervisorApprove')
        ->assertActionHidden('returnToStage');
})->with([RoleName::ApProcessor, RoleName::ApManager]);
