<?php

use App\Enums\RfpRequestType;
use App\Enums\RfpStatus;
use App\Enums\RfpVendorType;
use App\Filament\Resources\Rfps\Pages\CreateRfp;
use App\Filament\Resources\Rfps\Pages\EditRfp;
use App\Filament\Resources\Rfps\Pages\ListRfps;
use App\Filament\Resources\Rfps\Pages\ViewRfp;
use App\Filament\Resources\Rfps\Schemas\RfpForm;
use App\Models\AccountGroup;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\Rfp\DueType;
use App\Models\Rfp\Rfp;
use App\Models\Rfp\Subcategory;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\AccountGroupSeeder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\DemoMasterDataSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\Rfp\ApprovalMatrixSeeder;
use Database\Seeders\Rfp\DueTypeSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        CompanySeeder::class, CurrencySeeder::class, AccountGroupSeeder::class, PaymentMethodSeeder::class,
        DemoMasterDataSeeder::class, RoleAndUserSeeder::class, ApprovalMatrixSeeder::class, DueTypeSeeder::class,
    ]);

    Storage::fake('local');

    $this->requestor = User::firstWhere('email', 'requestor@ramcar.com');
    $this->company = Company::firstWhere('code', '1000');
    $this->officeSupplies = Subcategory::firstWhere('name', 'Office Supplies');
    $this->financeCostCenter = CostCenter::query()->where('company_id', $this->company->id)->where('description', 'Finance & Accounting')->first();
    $this->vendor = Vendor::firstWhere('code', '200001');
    $this->php = Currency::firstWhere('code', 'PHP');
    $this->standard = DueType::firstWhere('code', 'standard');

    $this->actingAs($this->requestor);
    $this->undoRepeaterFake = Repeater::fake();
});

afterEach(function () {
    ($this->undoRepeaterFake)();
});

/**
 * @return array<string, mixed>
 */
function rfpItem(array $overrides = []): array
{
    return array_replace([
        'description' => 'Bond paper and toner',
        'rfp_subcategory_id' => test()->officeSupplies->id,
        'cost_center_id' => test()->financeCostCenter->id,
        'invoice_number' => 'INV-1001',
        'invoice_date' => now()->subDays(2)->toDateString(),
        'currency_id' => test()->php->id,
        'amount' => '1500.25',
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function rfpFormData(array $overrides = []): array
{
    return array_replace([
        'rfp_due_type_id' => test()->standard->id,
        'request_type' => RfpRequestType::Standard->value,
        'company_id' => test()->company->id,
        'account_group_id' => test()->vendor->account_group_id,
        'vendor_id' => test()->vendor->id,
        'payment_method_id' => PaymentMethod::firstWhere('code', 'T')->id,
        'purpose' => 'Office supplies for Q4',
        'items' => [
            rfpItem(),
            rfpItem(['invoice_number' => 'INV-1002', 'amount' => '499.75']),
        ],
    ], $overrides);
}

function withDocuments(array $item): array
{
    return $item + ['attachments' => [UploadedFile::fake()->create('invoice.pdf', 120, 'application/pdf')]];
}

it('lets requestors open the list and the create page', function () {
    $this->get('/hub/rfps')->assertOk();
    $this->get('/hub/rfps/create')->assertOk();
});

it('keeps non-requestors out', function () {
    $this->actingAs(User::firstWhere('email', 'ap.processor@ramcar.com'))
        ->get('/hub/rfps')
        ->assertForbidden();
});

it('saves a draft with line items, totals, a number and a due date', function () {
    $this->travelTo(now()->next('Monday'));

    Livewire::test(CreateRfp::class)
        ->fillForm(rfpFormData())
        ->call('create')
        ->assertHasNoFormErrors();

    $rfp = Rfp::sole();

    expect($rfp->status)->toBe(RfpStatus::Draft)
        ->and($rfp->requestor_id)->toBe($this->requestor->id)
        ->and($rfp->rfp_number)->toBe(sprintf('RFP-%s-%06d', now()->format('Y'), $rfp->id))
        ->and($rfp->total_php_amount)->toBe('2000.00')
        ->and($rfp->due_date->toDateString())->toBe(today()->addWeek()->toDateString())
        ->and($rfp->items)->toHaveCount(2)
        ->and($rfp->items->first()->gl_account_id)->toBe($this->officeSupplies->gl_account_id)
        ->and($rfp->items->pluck('sort')->all())->toBe([1, 2]);
});

it('converts each line to PHP at its own currency rate', function () {
    Livewire::test(CreateRfp::class)
        ->fillForm(rfpFormData(['items' => [
            rfpItem(['currency_id' => Currency::firstWhere('code', 'USD')->id, 'amount' => '2000']),
            rfpItem(['amount' => '500']),
        ]]))
        ->call('create')
        ->assertHasNoFormErrors();

    $rfp = Rfp::sole();

    expect($rfp->total_php_amount)->toBe('117000.00')
        ->and($rfp->items->first())->exchange_rate->toBe('58.250000')->php_amount->toBe('116500.00')
        ->and($rfp->foreignTotals())->toBe(['USD' => '2000.00']);
});

it('requires a cost center when the expense needs one', function () {
    Livewire::test(CreateRfp::class)
        ->fillForm(rfpFormData(['items' => [rfpItem(['cost_center_id' => null])]]))
        ->call('create')
        ->assertHasFormErrors(['items.0.cost_center_id' => 'required']);
});

it('charges a PO line to a PO-type sub category without a cost center', function () {
    Livewire::test(CreateRfp::class)
        ->fillForm(rfpFormData(['items' => [
            rfpItem(['cost_center_id' => null, 'po_number' => '4500001234', 'rfp_subcategory_id' => Subcategory::firstWhere('name', 'Supplier Down Payment')->id]),
        ]]))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Rfp::sole()->items->first())->cost_center_id->toBeNull()->po_number->toBe('4500001234');
});

it('labels cost centers OPEX or MFG from the third digit', function () {
    expect(RfpForm::costCenterOptions($this->company->id))
        ->toContain('101200 · Finance & Accounting (OPEX)')
        ->toContain('103100 · Plant Operations (MFG)');
});

it('offers only sub categories that fit the cost center or PO', function () {
    $plant = CostCenter::query()->where('company_id', $this->company->id)->where('description', 'Plant Operations')->first();
    $names = fn (array $options): array => collect($options)->flatMap(fn (array $group): array => array_values($group))->all();

    expect($names(RfpForm::subcategoryOptions($this->financeCostCenter->id)))
        ->toContain('Office Supplies · 6021010')
        ->not->toContain('Factory Supplies · 5021010')
        ->not->toContain('Supplier Down Payment · 1151010')
        ->and($names(RfpForm::subcategoryOptions($plant->id)))
        ->toContain('Factory Supplies · 5021010')
        ->not->toContain('Office Supplies · 6021010')
        ->and($names(RfpForm::subcategoryOptions(null, '4500001234')))->toBe(['Supplier Down Payment · 1151010'])
        ->and(RfpForm::subcategoryOptions(null))->toBe([]);
});

it('rejects a manufacturing G/L on an OPEX cost center', function () {
    Livewire::test(CreateRfp::class)
        ->fillForm(rfpFormData(['items' => [rfpItem(['rfp_subcategory_id' => Subcategory::firstWhere('name', 'Factory Supplies')->id])]]))
        ->call('create')
        ->assertHasFormErrors(['items.0.rfp_subcategory_id']);

    expect(Rfp::count())->toBe(0);
});

it('saves and submits in one step when every line has documents', function () {
    Livewire::test(CreateRfp::class)
        ->fillForm(rfpFormData(['items' => [withDocuments(rfpItem())]]))
        ->callAction('createAndSubmit')
        ->assertHasNoFormErrors();

    $rfp = Rfp::sole();

    expect($rfp->status)->toBe(RfpStatus::ForApReview)
        ->and($rfp->submitted_at)->not->toBeNull()
        ->and($rfp->histories)->toHaveCount(1)
        ->and($rfp->histories->first()->to_status)->toBe(RfpStatus::ForApReview);

    Storage::disk('local')->assertExists($rfp->items->first()->attachments[0]);
});

it('keeps the draft when a line has no documents', function () {
    Livewire::test(CreateRfp::class)
        ->fillForm(rfpFormData(['items' => [withDocuments(rfpItem()), rfpItem()]]))
        ->callAction('createAndSubmit')
        ->assertNotified('Saved as draft, but not submitted');

    expect(Rfp::sole()->status)->toBe(RfpStatus::Draft);
});

it('submits a saved draft from the view page', function () {
    $rfp = Rfp::factory()->for($this->requestor, 'requestor')->for($this->company)->create();
    $rfp->items()->create(rfpItem(['amount' => '250.00', 'attachments' => ['rfp-attachments/invoice.pdf']]));

    Livewire::test(ViewRfp::class, ['record' => $rfp->getKey()])
        ->callAction('submit')
        ->assertNotified();

    expect($rfp->refresh()->status)->toBe(RfpStatus::ForApReview);
});

it('locks the request once submitted', function () {
    $rfp = Rfp::factory()->for($this->requestor, 'requestor')->for($this->company)->create();
    $rfp->items()->create(rfpItem(['amount' => '250.00', 'attachments' => ['rfp-attachments/invoice.pdf']]));
    $rfp->submit($this->requestor);

    $this->get("/hub/rfps/{$rfp->id}/edit")->assertForbidden();

    Livewire::test(ViewRfp::class, ['record' => $rfp->getKey()])
        ->assertActionHidden('submit')
        ->assertActionHidden('edit');
});

it('shows requestors only their own requests', function () {
    $mine = Rfp::factory()->for($this->requestor, 'requestor')->for($this->company)->create();
    $theirs = Rfp::factory()->for(User::factory()->create(), 'requestor')->for($this->company)->create();

    Livewire::test(ListRfps::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);

    $this->get("/hub/rfps/{$theirs->id}")->assertNotFound();
});

it('previews the full approval route', function () {
    expect(RfpForm::approvalRoute(RfpRequestType::Reimbursement, '50000', false))
        ->toBe('AP Processor → Line Manager → Department Head → AP Processor → AP Supervisor → AP Manager → Treasury')
        ->and(RfpForm::approvalRoute(RfpRequestType::Standard, '150000', true))
        ->toBe('AP Processor → Division Head → Enterprise Controller → AP Processor → AP Supervisor → AP Manager → Treasury');
});

it('lets the requestor edit a draft', function () {
    $rfp = Rfp::factory()->for($this->requestor, 'requestor')->for($this->company)->create();

    Livewire::test(EditRfp::class, ['record' => $rfp->getKey()])
        ->fillForm(rfpFormData(['purpose' => 'Updated purpose', 'items' => [rfpItem(['amount' => '75.50'])]]))
        ->call('save')
        ->assertHasNoFormErrors();

    expect($rfp->refresh())
        ->purpose->toBe('Updated purpose')
        ->total_php_amount->toBe('75.50');
});

it('counts the due date from submission, skipping weekends', function () {
    $this->travelTo(now()->next('Friday'));

    $rfp = Rfp::factory()->for($this->requestor, 'requestor')->for($this->company)->create([
        'rfp_due_type_id' => DueType::firstWhere('code', 'urgent')->id,
    ]);
    $rfp->items()->create(rfpItem(['attachments' => ['rfp-attachments/invoice.pdf']]));

    $rfp->submit($this->requestor);

    expect($rfp->refresh()->due_date->toDateString())->toBe(now()->next('Monday')->toDateString());
});

it('only offers payees of the account group extended to the paying company', function () {
    $employeeGroup = AccountGroup::firstWhere('code', '0013');

    Livewire::test(CreateRfp::class)
        ->fillForm(['company_id' => $this->company->id, 'account_group_id' => $employeeGroup->id])
        ->assertFormFieldExists('vendor_id', fn ($field): bool => collect($field->getOptions())->keys()->all() === Vendor::query()->where('account_group_id', $employeeGroup->id)->orderBy('name')->pluck('id')->all());
});

it("limits payment methods to the payee's methods unless it is a reimbursement", function () {
    Livewire::test(CreateRfp::class)
        ->fillForm(rfpFormData())
        ->assertFormFieldExists('payment_method_id', fn ($field): bool => array_values($field->getOptions()) === ['Bank Transfer', 'Check'])
        ->fillForm(['request_type' => RfpRequestType::Reimbursement->value])
        ->assertFormFieldExists('payment_method_id', fn ($field): bool => count($field->getOptions()) === PaymentMethod::count());
});

it('records who the requestor paid on reimbursement lines', function () {
    $finalPayee = Vendor::firstWhere('code', '900001');

    Livewire::test(CreateRfp::class)
        ->fillForm(rfpFormData([
            'request_type' => RfpRequestType::Reimbursement->value,
            'account_group_id' => $finalPayee->account_group_id,
            'vendor_id' => $finalPayee->id,
            'items' => [rfpItem(['company_id' => $this->company->id, 'vendor_type' => RfpVendorType::Vendor->value, 'vendor_id' => $this->vendor->id])],
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Rfp::sole()->items->first())
        ->vendor_type->toBe(RfpVendorType::Vendor)
        ->paidToName()->toBe($this->vendor->name);
});

it('requires the vendor on reimbursement lines', function () {
    Livewire::test(CreateRfp::class)
        ->fillForm(rfpFormData([
            'request_type' => RfpRequestType::Reimbursement->value,
            'items' => [rfpItem(['company_id' => $this->company->id, 'vendor_type' => RfpVendorType::Vendor->value])],
        ]))
        ->call('create')
        ->assertHasFormErrors(['items.0.vendor_id' => 'required']);
});

it('requires proof of approval when pre-approved and skips the matrix', function () {
    Livewire::test(CreateRfp::class)
        ->fillForm(rfpFormData(['is_pre_approved' => true]))
        ->call('create')
        ->assertHasFormErrors(['pre_approval_documents' => 'required']);

    expect(RfpForm::approvalRoute(RfpRequestType::Standard, '150000', false, isPreApproved: true))
        ->toBe('AP Processor → Pre-approved (no matrix) → AP Processor → AP Supervisor → AP Manager → Treasury');
});

it('shows the reimbursement columns only on a reimbursement', function () {
    Livewire::test(CreateRfp::class)
        ->assertSeeHtml('Documents')
        ->assertDontSeeHtml('Vendor company')
        ->fillForm(['request_type' => RfpRequestType::Reimbursement->value])
        ->assertSeeHtml('Vendor company');
});

it('drops who was paid when a reimbursement becomes standard', function () {
    $rfp = Rfp::factory()->for($this->requestor, 'requestor')->for($this->company)->create(['request_type' => RfpRequestType::Reimbursement]);
    $item = $rfp->items()->create(rfpItem(['company_id' => $this->company->id, 'vendor_type' => RfpVendorType::Vendor, 'vendor_id' => $this->vendor->id]));

    $rfp->update(['request_type' => RfpRequestType::Standard]);
    $item->refresh()->update(['description' => 'Edited']);

    expect($item->refresh())->vendor_id->toBeNull()->vendor_type->toBeNull();
});

it('finds requests by the invoice number of a line', function () {
    $match = Rfp::factory()->for($this->requestor, 'requestor')->for($this->company)->create();
    $match->items()->create(rfpItem(['invoice_number' => 'RTI-10052026']));
    $other = Rfp::factory()->for($this->requestor, 'requestor')->for($this->company)->create();
    $other->items()->create(rfpItem(['invoice_number' => 'INV-9999']));

    Livewire::test(ListRfps::class)
        ->searchTable('RTI-1005')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other]);
});

it('sorts by RFP number, created date and last update', function (string $column) {
    $older = Rfp::factory()->for($this->requestor, 'requestor')->for($this->company)->create(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
    $newer = Rfp::factory()->for($this->requestor, 'requestor')->for($this->company)->create();

    Livewire::test(ListRfps::class)
        ->sortTable($column)
        ->assertCanSeeTableRecords([$older, $newer], inOrder: true)
        ->sortTable($column, 'desc')
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
})->with(['rfp_number', 'created_at', 'updated_at']);
