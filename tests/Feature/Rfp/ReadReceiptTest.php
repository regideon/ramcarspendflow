<?php

use App\Ai\Agents\ReceiptReader;
use App\Enums\RfpRequestType;
use App\Enums\RfpStatus;
use App\Enums\RfpVendorType;
use App\Filament\Resources\Rfps\Pages\CreateRfp;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\Rfp\DueType;
use App\Models\Rfp\Item;
use App\Models\Rfp\OneTimeVendor;
use App\Models\Rfp\ReceiptRead;
use App\Models\Rfp\Rfp;
use App\Models\Rfp\Subcategory;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Rfp\ReceiptScanner;
use Database\Seeders\AccountGroupSeeder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\DemoMasterDataSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\Rfp\ApprovalMatrixSeeder;
use Database\Seeders\Rfp\DueTypeSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        CompanySeeder::class, CurrencySeeder::class, AccountGroupSeeder::class, PaymentMethodSeeder::class,
        DemoMasterDataSeeder::class, RoleAndUserSeeder::class, ApprovalMatrixSeeder::class, DueTypeSeeder::class,
    ]);

    Storage::fake('local');
    config(['ai.providers.openai.key' => 'test-key', 'services.receipt_reader.enabled' => true]);

    $this->company = Company::firstWhere('code', '1000');
    $this->vendor = Vendor::firstWhere('code', '200001'); // TIN 000-200-001-000
    $this->officeSupplies = Subcategory::firstWhere('name', 'Office Supplies');
    $this->financeCostCenter = CostCenter::query()->where('company_id', $this->company->id)->where('description', 'Finance & Accounting')->first();

    $this->actingAs(User::firstWhere('email', 'requestor@ramcar.com'));
    $this->undoRepeaterFake = Repeater::fake();
});

afterEach(function () {
    ($this->undoRepeaterFake)();
});

/**
 * @return array<string, mixed>
 */
function receiptRead(array $overrides = []): array
{
    return array_replace([
        'file' => 1,
        'page' => 1,
        'document_type' => 'sales_invoice',
        'invoice_number' => 'SI-0045821',
        'invoice_date' => now()->subDays(3)->toDateString(),
        'vendor_name' => 'Acme Office Supply Inc.',
        'vendor_tin' => '000-200-001-00000',
        'currency' => 'PHP',
        'total_amount' => 1120.00,
        'vatable_amount' => 1000.00,
        'vat_amount' => 120.00,
        'description' => 'Office supplies: bond paper, toner',
        'purpose' => 'Payment for office supplies from Acme Office Supply, SI-0045821.',
        'suggested_subcategory' => 'Office Supplies',
        'confidence' => ['invoice_number' => 'high', 'invoice_date' => 'high', 'vendor' => 'high', 'amount' => 'low'],
        'notes' => null,
    ], $overrides);
}

/**
 * ReceiptReader output: one entry per receipt.
 *
 * @return array{receipts: list<array<string, mixed>>}
 */
function receiptReads(array ...$receipts): array
{
    return ['receipts' => array_map(fn (array $overrides): array => receiptRead($overrides), $receipts ?: [[]])];
}

function receiptForm(array $overrides = []): array
{
    return array_replace([
        'rfp_due_type_id' => DueType::firstWhere('code', 'standard')->id,
        'request_type' => RfpRequestType::Standard->value,
        'company_id' => test()->company->id,
        'account_group_id' => test()->vendor->account_group_id,
        'vendor_id' => test()->vendor->id,
        'payment_method_id' => PaymentMethod::firstWhere('code', 'T')->id,
        'purpose' => null,
        'items' => [[
            'cost_center_id' => test()->financeCostCenter->id,
            'currency_id' => Currency::firstWhere('code', 'PHP')->id,
            'attachments' => [UploadedFile::fake()->image('receipt.jpg')],
        ]],
    ], $overrides);
}

it('reads the receipt and applies the reviewed values to the item', function () {
    ReceiptReader::fake([receiptReads()])->preventStrayPrompts();

    Livewire::test(CreateRfp::class)
        ->fillForm(receiptForm())
        ->mountAction(TestAction::make('readReceipt')->schemaComponent('items.0.attachments'))
        ->assertSchemaStateSet([
            'receipts.0.invoice_number' => 'SI-0045821',
            'receipts.0.amount' => 1120.0,
            'receipts.0.rfp_subcategory_id' => $this->officeSupplies->id,
        ])
        ->setActionData(['receipts.0.invoice_number' => 'SI-0045821-A']) // the requestor corrects a value
        ->callMountedAction()
        ->assertNotified('Applied to the item')
        ->assertFormSet([
            'items.0.invoice_number' => 'SI-0045821-A',
            'items.0.amount' => 1120.0,
            'items.0.description' => 'Office supplies: bond paper, toner',
            'items.0.rfp_subcategory_id' => $this->officeSupplies->id,
            'purpose' => 'Payment for office supplies from Acme Office Supply, SI-0045821.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $item = Item::firstWhere('invoice_number', 'SI-0045821-A');

    expect($item->receipt_scan)
        ->toHaveKeys(['read_at', 'read_by', 'model', 'result', 'applied_at'])
        ->and($item->receipt_scan['result']['invoice_number'])->toBe('SI-0045821');

    ReceiptReader::assertPrompted(fn ($prompt): bool => count($prompt->attachments) === 1);
});

it('adds an item for each further receipt, with its own document and the same charging', function () {
    ReceiptReader::fake([receiptReads(
        ['file' => 1, 'invoice_number' => 'OR-1001', 'total_amount' => 500.00, 'description' => 'Taxi fare'],
        ['file' => 2, 'invoice_number' => 'OR-1002', 'total_amount' => 750.00, 'description' => 'Parking fees', 'invoice_date' => null],
    )])->preventStrayPrompts();

    $first = UploadedFile::fake()->image('taxi.jpg');
    $second = UploadedFile::fake()->image('parking.jpg', 20, 30);

    Livewire::test(CreateRfp::class)
        ->fillForm(receiptForm(['items' => [[
            'cost_center_id' => $this->financeCostCenter->id,
            'currency_id' => Currency::firstWhere('code', 'PHP')->id,
            'attachments' => [$first, $second],
        ]]]))
        ->mountAction(TestAction::make('readReceipt')->schemaComponent('items.0.attachments'))
        ->assertSchemaStateSet([
            'receipts.0.invoice_number' => 'OR-1001',
            'receipts.1.invoice_number' => 'OR-1002',
            'receipts.1.file' => 1,
        ])
        ->callMountedAction()
        ->assertNotified('2 receipts applied')
        ->assertFormSet([
            'items.0.invoice_number' => 'OR-1001',
            'items.0.amount' => 500.0,
            'items.1.invoice_number' => 'OR-1002',
            'items.1.amount' => 750.0,
            'items.1.description' => 'Parking fees',
            'items.1.invoice_date' => null, // not carried over from the first receipt
            'items.1.cost_center_id' => $this->financeCostCenter->id,
        ])
        ->assertFormSet(function (array $state): array {
            $items = array_values($state['items']);

            expect($items[0]['attachments'])->toHaveCount(1)
                ->and($items[1]['attachments'])->toHaveCount(1)
                ->and(array_keys($items[0]['attachments']))->not->toBe(array_keys($items[1]['attachments']));

            return [];
        })
        ->set('data.items.1.invoice_date', today()->toDateString()) // left blank on the receipt: the requestor fills it in
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Item::orderBy('sort')->pluck('invoice_number')->all())->toBe(['OR-1001', 'OR-1002'])
        ->and(Item::firstWhere('invoice_number', 'OR-1002')->receipt_scan)->receipt->toBe(2)->receipts->toBe(2);

    ReceiptReader::assertPromptedTimes(1);
});

it('splits several receipts in one PDF, each item keeping the PDF', function () {
    ReceiptReader::fake([receiptReads(
        ['page' => 1, 'invoice_number' => 'OR-2001', 'total_amount' => 100.00],
        ['page' => 3, 'invoice_number' => 'OR-2002', 'total_amount' => 200.00],
    )]);

    Livewire::test(CreateRfp::class)
        ->fillForm(receiptForm(['items' => [[
            'cost_center_id' => $this->financeCostCenter->id,
            'currency_id' => Currency::firstWhere('code', 'PHP')->id,
            'attachments' => [UploadedFile::fake()->createWithContent('receipts.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n")],
        ]]]))
        ->mountAction(TestAction::make('readReceipt')->schemaComponent('items.0.attachments'))
        ->assertSchemaStateSet(['receipts.1.page' => 3, 'receipts.1.file' => 0])
        ->callMountedAction()
        ->assertFormSet([
            'items.0.invoice_number' => 'OR-2001',
            'items.1.invoice_number' => 'OR-2002',
        ])
        ->assertFormSet(function (array $state): array {
            expect(array_values($state['items'])[1]['attachments'])->toHaveCount(1);

            return [];
        });
});

it('skips a receipt the requestor removes from the review', function () {
    ReceiptReader::fake([receiptReads(['invoice_number' => 'OR-3001'], ['invoice_number' => 'OR-3002', 'file' => 1])]);

    Livewire::test(CreateRfp::class)
        ->fillForm(receiptForm())
        ->mountAction(TestAction::make('readReceipt')->schemaComponent('items.0.attachments'))
        ->setActionData(['receipts' => [receiptRead(['invoice_number' => 'OR-3001'])]])
        ->callMountedAction()
        ->assertNotified('Applied to the item')
        ->assertFormSet(['items.0.invoice_number' => 'OR-3001'])
        ->assertCount('data.items', 1);
});

it('warns about a receipt billed twice in the same read', function () {
    ReceiptReader::fake([receiptReads(['invoice_number' => 'OR-4001'], ['invoice_number' => 'OR-4001'])]);

    Livewire::test(CreateRfp::class)
        ->fillForm(receiptForm())
        ->mountAction(TestAction::make('readReceipt')->schemaComponent('items.0.attachments'))
        ->assertSchemaStateSet(function (array $state): array {
            $receipts = array_values($state['receipts']);

            expect($receipts[0]['warnings'])->toBe([])
                ->and(implode(' ', $receipts[1]['warnings']))->toContain('also on receipt 1 here');

            return [];
        });
});

it('still uses a read saved before several receipts were supported', function () {
    expect(ReceiptScanner::receipts(receiptRead(['file' => null, 'page' => null])))
        ->toHaveCount(1)
        ->{0}->invoice_number->toBe('SI-0045821')
        ->{0}->file->toBeNull()
        ->and(ReceiptScanner::receipts(receiptReads(['file' => 5]), fileCount: 2))->{0}->file->toBeNull(); // out of range
});

it('only reads PDFs and images', function () {
    Storage::disk('local')->put('rfp-attachments/list.csv', "a,b\n1,2\n");
    Storage::disk('local')->put('rfp-attachments/receipt.png', UploadedFile::fake()->image('receipt.png')->getContent());

    expect(ReceiptScanner::readableFiles(['rfp-attachments/list.csv', 'rfp-attachments/receipt.png', 'rfp-attachments/missing.pdf']))
        ->toHaveCount(1)
        ->{0}->mime->toBe('image/png');
});

it('shows the uploaded document beside the review to compare', function () {
    ReceiptReader::fake([receiptReads()]);

    Livewire::test(CreateRfp::class)
        ->fillForm(receiptForm())
        ->mountAction(TestAction::make('readReceipt')->schemaComponent('items.0.attachments'))
        ->assertSchemaStateSet(function (array $state): array {
            expect($state['documents'])->toHaveCount(1)
                ->{0}->mime->toBe('image/jpeg')
                ->{0}->name->toBe('receipt.jpg')
                ->{0}->url->toContain('/rfp/receipt-preview');

            return [];
        });
});

it('serves a supporting document only through its signed preview link', function () {
    Storage::disk('local')->put('rfp-attachments/receipt.png', UploadedFile::fake()->image('receipt.png')->getContent());
    Storage::disk('local')->put('secrets/other.png', UploadedFile::fake()->image('other.png')->getContent());

    $url = ReceiptScanner::previewFiles(['rfp-attachments/receipt.png'])[0]['url'];

    $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');

    // Tampered or unsigned links, files outside the supporting documents, and guests are refused
    $this->get($url.'x')->assertForbidden();
    $this->get(URL::temporarySignedRoute('rfp.receipt-preview', now()->addHour(), ['file' => Crypt::encryptString(json_encode(['local', 'secrets/other.png']))]))->assertNotFound();

    auth()->logout();
    $this->get($url)->assertForbidden();
});

it('keeps a purpose the requestor already wrote, showing the AI suggestion', function () {
    ReceiptReader::fake([receiptReads()]);

    Livewire::test(CreateRfp::class)
        ->fillForm(receiptForm(['purpose' => 'Q4 office supplies']))
        ->mountAction(TestAction::make('readReceipt')->schemaComponent('items.0.attachments'))
        ->assertSchemaStateSet(['purpose' => 'Q4 office supplies', 'suggested_purpose' => 'Payment for office supplies from Acme Office Supply, SI-0045821.'])
        ->callMountedAction()
        ->assertFormSet(['purpose' => 'Q4 office supplies']);
});

it('asks for the document when Read receipt is clicked without one', function () {
    ReceiptReader::fake()->preventStrayPrompts();

    Livewire::test(CreateRfp::class)
        ->fillForm(receiptForm())
        ->fillForm(['items.0.attachments' => []]) // the file was removed, but the button is still on screen
        ->mountAction(TestAction::make('readReceipt')->schemaComponent('items.0.attachments'))
        ->assertActionNotMounted(TestAction::make('readReceipt')->schemaComponent('items.0.attachments'))
        ->assertNotified('Upload the document first');

    ReceiptReader::assertNeverPrompted();
});

it('closes the review with a message when the reader fails', function () {
    ReceiptReader::fake(fn () => throw new RuntimeException('OpenAI is down'));

    Livewire::test(CreateRfp::class)
        ->fillForm(receiptForm())
        ->mountAction(TestAction::make('readReceipt')->schemaComponent('items.0.attachments'))
        ->assertNotified('Couldn\'t read the document')
        ->assertFormSet(['items.0.invoice_number' => null]);
});

it('reuses the saved read when the same document is read again', function () {
    ReceiptReader::fake([receiptReads()]);

    $page = Livewire::test(CreateRfp::class)->fillForm(receiptForm());
    $action = TestAction::make('readReceipt')->schemaComponent('items.0.attachments');

    $page->mountAction($action)->assertSchemaStateSet(['reused' => false]);
    $page->call('unmountAction'); // closed by accident

    $page->mountAction($action)
        ->assertSchemaStateSet(['reused' => true, 'receipts.0.invoice_number' => 'SI-0045821']);

    ReceiptReader::assertPromptedTimes(1);
    expect(ReceiptRead::count())->toBe(1)
        ->and(ReceiptRead::first())->reader_version->toBe(ReceiptReader::VERSION)->result->receipts->{0}->invoice_number->toBe('SI-0045821');
});

it('reads the document again after the reader changes', function () {
    ReceiptReader::fake([receiptReads()]);
    $image = UploadedFile::fake()->image('receipt.jpg');

    ReceiptRead::create(['file_hash' => hash_file('sha256', $image->getRealPath()), 'reader_version' => ReceiptReader::VERSION - 1, 'result' => receiptReads(['invoice_number' => 'OLD'])]);

    Livewire::test(CreateRfp::class)
        ->fillForm(receiptForm(['items' => [['attachments' => [$image]]]]))
        ->mountAction(TestAction::make('readReceipt')->schemaComponent('items.0.attachments'))
        ->assertSchemaStateSet(['reused' => false, 'receipts.0.invoice_number' => 'SI-0045821']);

    ReceiptReader::assertPromptedTimes(1);
});

it('limits how many new documents a user can read per hour', function () {
    config(['services.receipt_reader.per_hour' => 1]);
    ReceiptReader::fake([receiptReads(), receiptReads()]);

    $action = TestAction::make('readReceipt')->schemaComponent('items.0.attachments');

    Livewire::test(CreateRfp::class)
        ->fillForm(receiptForm())
        ->mountAction($action)
        ->callMountedAction()
        ->fillForm(['items.0.attachments' => [UploadedFile::fake()->image('another.jpg', 20, 30)]])
        ->mountAction($action)
        ->assertNotified('Too many receipts read');

    ReceiptReader::assertPromptedTimes(1);
});

it('sets who was paid on a reimbursement, using a one-time vendor when no vendor has the TIN', function () {
    ReceiptReader::fake([receiptReads(['vendor_name' => 'Jollibee Foods Corp.', 'vendor_tin' => '000-388-474-00000'])]);

    Livewire::test(CreateRfp::class)
        ->fillForm(receiptForm(['request_type' => RfpRequestType::Reimbursement->value]))
        ->callAction(TestAction::make('readReceipt')->schemaComponent('items.0.attachments'))
        ->assertFormSet([
            'items.0.vendor_type' => RfpVendorType::OneTimeVendor,
            'items.0.rfp_one_time_vendor_id' => OneTimeVendor::firstWhere('tin', '000-388-474-000')?->id,
        ]);

    expect(OneTimeVendor::where('name', 'Jollibee Foods Corp.')->count())->toBe(1)
        ->and(app(ReceiptScanner::class)->formatTin('000388474 00002'))->toBe('000-388-474-002');
});

it('matches a vendor by TIN, ignoring dashes and the branch code', function () {
    expect(app(ReceiptScanner::class)->vendorByTin('000200001'))->id->toBe($this->vendor->id)
        ->and(app(ReceiptScanner::class)->vendorByTin('000-200-001-00002'))->id->toBe($this->vendor->id)
        ->and(app(ReceiptScanner::class)->vendorByTin('123'))->toBeNull();
});

it('flags what the requestor should check', function () {
    $other = Vendor::query()->where('id', '!=', $this->vendor->id)->whereNotNull('tax_number')->first();
    $rfp = Rfp::factory()->for($this->company)->create(['status' => RfpStatus::ForApReview]);
    Item::factory()->for($rfp)->create(['invoice_number' => 'SI-0045821', 'amount' => 1120.00]);

    $review = app(ReceiptScanner::class)->review(receiptRead([
        'invoice_date' => now()->subYear()->toDateString(),
        'currency' => 'XYZ',
        'vat_amount' => 50.00,
        'notes' => 'Handwritten amount',
    ]), context: ['payee_id' => $other->id]);

    expect($review['warnings'])->toHaveCount(6)
        ->and(implode(' ', $review['warnings']))
        ->toContain('outside what an RFP accepts')
        ->toContain('XYZ is not set up')
        ->toContain('not 12% of the VATable sales')
        ->toContain("already on {$rfp->rfp_number}")
        ->toContain('not the vendor on this RFP')
        ->toContain('Handwritten amount')
        ->and($review['fields']['currency_id'])->toBeNull()
        ->and($review['fields']['rfp_subcategory_id'])->toBeNull(); // no allowed sub categories given
});
