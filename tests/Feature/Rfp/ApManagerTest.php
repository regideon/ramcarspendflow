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
    $this->manager = User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::ApManager));
    $this->treasury = User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::Treasury));
    $this->inputTax = TaxCode::firstWhere('code', 'I2'); // 12%
    $this->withholding = WithholdingTaxCode::firstWhere('code', '04'); // 2%

    $this->undoRepeaterFake = Repeater::fake();
});

afterEach(function () {
    ($this->undoRepeaterFake)();
});

/**
 * An RFP coded by the AP Processor (I2, 2%) and approved by the AP Supervisor, now with the AP Manager.
 */
function rfpWithManager(): Rfp
{
    $rfp = Rfp::factory()->for(test()->requestor, 'requestor')->for(Company::firstWhere('code', '1000'))->create();
    Item::factory()->for($rfp)->create(['amount' => '11200.00', 'invoice_number' => 'INV-1']);
    $rfp->submit(test()->requestor);
    $engine = app(ApprovalEngine::class);
    $engine->approve($rfp, test()->apProcessor, verb: 'Endorsed');
    app(RfpTaxProcessing::class)->process($rfp, test()->apProcessor, managerLines($rfp));
    $engine->approve($rfp, User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::ApSupervisor)));

    return $rfp->refresh();
}

/**
 * @return list<array<string, mixed>>
 */
function managerLines(Rfp $rfp, array $override = []): array
{
    return $rfp->items->map(fn (Item $item): array => [
        'id' => $item->id,
        'amount' => (string) $item->amount,
        'tax_code_id' => test()->inputTax->id,
        'withholding_tax_code_id' => test()->withholding->id,
        ...$override,
    ])->all();
}

it('completes the accounting review with the payment type and sends the request to Treasury', function () {
    $rfp = rfpWithManager();
    expect($rfp->current_approver_role)->toBe(RoleName::ApManager);

    $this->actingAs($this->manager);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->callAction('managerComplete', ['is_ids' => false, 'remarks' => 'OK to pay'])
        ->assertHasNoActionErrors();

    expect($rfp->refresh())
        ->status->toBe(RfpStatus::ForTreasury)
        ->current_approver_role->toBe(RoleName::Treasury)
        ->is_ids->toBeFalse()
        ->and($rfp->histories()->latest('id')->first())->action->toBe('Completed (AP Manager)')->remarks->toBe('OK to pay')
        ->and($this->treasury->notifications()->latest()->first()->data['title'])->toBe("{$rfp->rfp_number} is waiting for you");

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->assertSee('Non-IDS (manual)');
});

it('lets the AP Manager change the withholding tax', function () {
    $rfp = rfpWithManager();
    $fivePercent = WithholdingTaxCode::firstWhere('rate', 5);
    $this->actingAs($this->manager);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->callAction('managerComplete', ['lines' => managerLines($rfp, ['withholding_tax_code_id' => $fivePercent->id])])
        ->assertHasNoActionErrors();

    expect($rfp->items()->first())->withholding_tax_code_id->toBe($fivePercent->id)->withholding_tax_amount->toBe('500.00');
});

it('keeps the input tax locked for the AP Manager', function () {
    $rfp = rfpWithManager();
    $this->actingAs($this->manager);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->callAction('managerComplete', ['lines' => managerLines($rfp, ['tax_code_id' => TaxCode::firstWhere('code', 'I3')->id])])
        ->assertNotified('Not saved yet');

    expect($rfp->refresh()->current_approver_role)->toBe(RoleName::ApManager)
        ->and($rfp->items()->first()->tax_code_id)->toBe($this->inputTax->id);
});

it('lets the AP Manager return the request to the AP Processor or cancel it', function (string $action, RfpStatus $after) {
    $rfp = rfpWithManager();
    $this->actingAs($this->manager);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->assertActionVisible($action)
        ->callAction($action, ['remarks' => 'Not for payment'])
        ->assertHasNoActionErrors();

    expect($rfp->refresh()->status)->toBe($after);
})->with([
    'return to AP Processor' => ['returnToStage', RfpStatus::ForApProcessing],
    'cancel' => ['cancel', RfpStatus::Cancelled],
]);

it('offers the AP Manager no return to the requestor', function () {
    $rfp = rfpWithManager();
    $this->actingAs($this->manager);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->assertActionHidden('returnForRevision')
        ->assertActionHidden('supervisorApprove');
});

it('shows the final review to nobody else', function (RoleName $role) {
    $rfp = rfpWithManager();
    $this->actingAs(User::firstWhere('email', RoleAndUserSeeder::emailFor($role)));

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->assertActionHidden('managerComplete')
        ->assertActionHidden('cancel');
})->with([RoleName::ApSupervisor, RoleName::ApProcessor]);
