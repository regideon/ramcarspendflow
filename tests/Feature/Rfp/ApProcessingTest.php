<?php

use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Resources\RfpApprovals\Pages\ViewRfpApproval;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Rfp\Item;
use App\Models\Rfp\Rfp;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\WithholdingTaxCode;
use App\Services\Rfp\ApprovalEngine;
use App\Services\Rfp\RfpTaxCalculator;
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
    $this->inputTax = TaxCode::firstWhere('code', 'I2'); // 12%
    $this->withholding = WithholdingTaxCode::firstWhere('code', '04'); // 2%

    $this->undoRepeaterFake = Repeater::fake();
});

afterEach(function () {
    ($this->undoRepeaterFake)();
});

/**
 * An RFP endorsed at AP review; with the matrix parked it is now in AP processing.
 *
 * @param  list<string>  $amounts  one PHP line per amount
 */
function rfpInApProcessing(array $amounts = ['11200.00', '5600.00']): Rfp
{
    $rfp = Rfp::factory()->for(test()->requestor, 'requestor')->for(Company::firstWhere('code', '1000'))->create();

    foreach ($amounts as $index => $amount) {
        Item::factory()->for($rfp)->create(['amount' => $amount, 'sort' => $index + 1, 'invoice_number' => 'INV-'.($index + 1)]);
    }

    $rfp->submit(test()->requestor);
    app(ApprovalEngine::class)->approve($rfp, test()->apProcessor, verb: 'Endorsed');

    return $rfp->refresh();
}

/**
 * Form lines for the RFP's items, every line coded unless overridden.
 *
 * @param  array<int, array<string, mixed>>  $overrides  by line index
 * @return list<array<string, mixed>>
 */
function taxLines(Rfp $rfp, array $overrides = []): array
{
    return $rfp->items()->orderBy('sort')->get()->values()->map(fn (Item $item, int $index): array => [
        'id' => $item->id,
        'amount' => (string) $item->amount,
        'tax_code_id' => test()->inputTax->id,
        'withholding_tax_code_id' => test()->withholding->id,
        ...($overrides[$index] ?? []),
    ])->all();
}

it('works out VAT and withholding from a VAT-inclusive gross', function () {
    expect(app(RfpTaxCalculator::class)->forLine('11200.00', $this->inputTax, $this->withholding))->toBe([
        'vat_amount' => '1200.00',
        'net_of_vat_amount' => '10000.00',
        'withholding_tax_amount' => '200.00',
        'net_of_withholding_tax_amount' => '11000.00',
    ])
        ->and(app(RfpTaxCalculator::class)->forLine('1000.00', TaxCode::firstWhere('code', 'IX'), $this->withholding))->toBe([
            'vat_amount' => '0.00',
            'net_of_vat_amount' => '1000.00',
            'withholding_tax_amount' => '20.00',
            'net_of_withholding_tax_amount' => '980.00',
        ]);
});

it('books the tax codes and sends the request to the AP Supervisor', function () {
    $rfp = rfpInApProcessing();
    expect($rfp->status)->toBe(RfpStatus::ForApProcessing);

    $this->actingAs($this->apProcessor);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->callAction('processTaxCodes', ['lines' => taxLines($rfp), 'remarks' => 'Coded'])
        ->assertHasNoActionErrors()
        ->assertNotified("{$rfp->rfp_number}: tax codes processed");

    $first = $rfp->items()->orderBy('sort')->first();

    expect($rfp->refresh())
        ->status->toBe(RfpStatus::ForAccountingApproval)
        ->current_approver_role->toBe(RoleName::ApSupervisor)
        ->and($first)
        ->tax_code_id->toBe($this->inputTax->id)
        ->withholding_tax_code_id->toBe($this->withholding->id)
        ->withholdingTaxType->code->toBe('EI')
        ->vat_amount->toBe('1200.00')
        ->net_of_withholding_tax_amount->toBe('11000.00')
        ->and($rfp->histories()->latest('id')->first())
        ->action->toBe('Tax codes processed (AP Processor)')
        ->remarks->toBe('Coded');
});

it('lets AP move gross between lines of a currency when the total stays the same', function () {
    $rfp = rfpInApProcessing();
    $this->actingAs($this->apProcessor);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->callAction('processTaxCodes', ['lines' => taxLines($rfp, [0 => ['amount' => '10000.00'], 1 => ['amount' => '6800.00']])])
        ->assertHasNoActionErrors();

    expect($rfp->items()->orderBy('sort')->pluck('php_amount')->all())->toBe(['10000.00', '6800.00'])
        ->and($rfp->refresh()->total_php_amount)->toBe('16800.00');
});

it('refuses to save when a currency no longer totals the same', function () {
    $rfp = rfpInApProcessing();
    $this->actingAs($this->apProcessor);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->callAction('processTaxCodes', ['lines' => taxLines($rfp, [0 => ['amount' => '10000.00']])])
        ->assertNotified('Not saved yet');

    expect($rfp->refresh()->status)->toBe(RfpStatus::ForApProcessing)
        ->and($rfp->items()->whereNotNull('tax_code_id')->exists())->toBeFalse();
});

it('keeps foreign lines at the rate captured on submit', function () {
    $usd = Currency::firstWhere('code', 'USD');
    $rfp = rfpInApProcessing(['1000.00']);
    $line = $rfp->items()->first();
    $line->forceFill(['currency_id' => $usd->id, 'exchange_rate' => '58.000000', 'php_amount' => '58000.00'])->saveQuietly();

    $this->actingAs($this->apProcessor);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->callAction('processTaxCodes', ['lines' => taxLines($rfp)])
        ->assertHasNoActionErrors();

    expect($line->refresh())->exchange_rate->toBe('58.000000')->php_amount->toBe('58000.00');
});

it('needs an input tax and a withholding tax on every line', function () {
    $rfp = rfpInApProcessing();
    $this->actingAs($this->apProcessor);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->callAction('processTaxCodes', ['lines' => taxLines($rfp, [1 => ['tax_code_id' => null, 'withholding_tax_code_id' => null]])])
        ->assertHasActionErrors();

    expect($rfp->refresh()->status)->toBe(RfpStatus::ForApProcessing);
});

it('returns for revision, and with the matrix kept a resubmission comes straight back to AP processing', function (bool $keep, RfpStatus $after) {
    config(['rfp.auto_approve_matrix' => false]);
    $rfp = Rfp::factory()->for($this->requestor, 'requestor')->for(Company::firstWhere('code', '1000'))->create();
    Item::factory()->for($rfp)->create(['amount' => 50_000]);
    $rfp->submit($this->requestor);
    $engine = app(ApprovalEngine::class);
    $engine->approve($rfp, $this->apProcessor);
    $engine->approve($rfp, User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::DepartmentHead)));
    $engine->approve($rfp, User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::BuFpa)));

    $this->actingAs($this->apProcessor);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->assertActionVisible('returnForRevision')
        ->callAction('returnForRevision', ['remarks' => 'Wrong payee bank', 'keep_matrix_approval' => $keep])
        ->assertHasNoActionErrors();

    $rfp->submit($this->requestor);

    expect($rfp->refresh()->status)->toBe($after);
})->with([
    'matrix kept' => [true, RfpStatus::ForApProcessing],
    'matrix not kept' => [false, RfpStatus::ForApproval],
]);

it('cancels with a reason, and tells the requestor', function () {
    $rfp = rfpInApProcessing();
    $this->actingAs($this->apProcessor);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->callAction('cancel', ['remarks' => ''])
        ->assertHasActionErrors(['remarks' => 'required']);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->callAction('cancel', ['remarks' => 'Duplicate of RFP-2026-000001'])
        ->assertHasNoActionErrors();

    expect($rfp->refresh()->status)->toBe(RfpStatus::Cancelled)
        ->and($this->requestor->notifications()->latest()->first()->data['title'])->toBe("{$rfp->rfp_number} was cancelled");
});

it('offers processing and cancel only at AP processing, and only to its AP Processor', function () {
    $rfp = rfpInApProcessing();

    $this->actingAs(User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::ApSupervisor)));

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->assertActionHidden('processTaxCodes')
        ->assertActionHidden('cancel');
});

it('does not offer cancel or tax processing at AP review', function () {
    $rfp = Rfp::factory()->for($this->requestor, 'requestor')->for(Company::firstWhere('code', '1000'))->create();
    Item::factory()->for($rfp)->create(['amount' => 1000]);
    $rfp->submit($this->requestor);

    $this->actingAs($this->apProcessor);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->assertActionVisible('endorse')
        ->assertActionVisible('returnForRevision')
        ->assertActionHidden('processTaxCodes')
        ->assertActionHidden('cancel');
});

it('shows the booked tax entries and the total net of withholding tax', function () {
    $rfp = rfpInApProcessing();
    $this->actingAs($this->apProcessor);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->callAction('processTaxCodes', ['lines' => taxLines($rfp)]);

    $this->actingAs(User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::ApSupervisor)));

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->assertSee('Tax entries')
        ->assertSee('I2')
        ->assertSee('PHP 16,500.00'); // 11,000 + 5,500 net of 2% withholding
});
