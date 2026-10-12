<?php

use App\Ai\Agents\VendorInvoiceReader;
use App\Enums\P2pStatus;
use App\Enums\P2pThreeWayMatchStatus;
use App\Enums\RoleName;
use App\Filament\Pages\P2p\BulkAccountsPayable;
use App\Filament\Pages\P2p\BulkGoodsReceipt;
use App\Filament\Resources\P2p\Approvals\Pages\ListApprovals;
use App\Models\Company;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\Item;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\User;
use App\Models\Vendor;
use App\Services\P2p\P2pInvoiceMatcher;
use Database\Seeders\RoleAndUserSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndUserSeeder::class);
    Storage::fake('public');
    Storage::disk('public')->put('attachments-pdf/invoice.pdf', "%PDF-1.4\n% vendor invoice");
    config(['ai.providers.openai.key' => 'test-key', 'services.invoice_reader.enabled' => true]);

    $this->vendor = Vendor::factory()->create();
    $this->company = Company::factory()->create();
    $this->vendorUser = User::factory()->create(['vendor_id' => $this->vendor->id])->assignRole(RoleName::Vendor);

    // Two GRs on two POs (the Sorfin case): a head cylinder and a pulley, priced in USD
    [$this->cylinder, $this->cylinderState] = invoiceGr('4100385065', '5002258048', 'E1112099', 'HEAD CYLINDER NASON', 1, 630.00);
    [$this->pulley, $this->pulleyState] = invoiceGr('4100385620', '5002258099', 'E9172096', 'V-BELT PULLEY UHMW', 8, 70.00);
});

/**
 * @return array{0: SapGoodReceiptItem, 1: GoodReceiptState}
 */
function invoiceGr(string $po, string $gr, string $code, string $name, int $quantity, float $price): array
{
    $receipt = SapGoodReceipt::factory()->create([
        'purchase_order' => $po, 'good_receipt_number' => $gr, 'vendor_id' => test()->vendor->id, 'company_id' => test()->company->id,
    ]);

    $item = SapGoodReceiptItem::factory()->for($receipt, 'goodReceipt')->create([
        'purchase_order' => $po, 'purchase_order_item' => '00010', 'item_name' => $name,
        'item_id' => Item::query()->create(['code' => $code, 'name' => $name, 'is_active' => true])->id,
        'quantity_po' => $quantity, 'quantity_served' => $quantity, 'unit_cost' => round($price * 58.25, 4),
        'net_price' => $price, 'price_unit' => 1, 'currency' => 'USD',
    ]);

    $state = GoodReceiptState::create([
        'p2p_sap_good_receipt_id' => $receipt->id, 'purchase_order' => $po, 'good_receipt_number' => $gr,
        'company_id' => test()->company->id, 'vendor_id' => test()->vendor->id, 'status' => P2pStatus::Pending,
    ]);

    return [$item, $state];
}

/**
 * What VendorInvoiceReader returns for the vendor's PDF.
 */
function sorfinRead(float $pulleyPrice = 70.00): array
{
    $line = fn (string $description, string $code, int $quantity, float $price): array => [
        'description' => $description, 'material_code' => $code, 'po_number' => null, 'gr_number' => null,
        'quantity' => $quantity, 'uom' => 'EA', 'unit_price' => $price, 'amount' => $quantity * $price, 'confidence' => 'high',
    ];

    return [
        'pages' => [['page' => 1, 'type' => 'import_permit'], ['page' => 2, 'type' => 'invoice']],
        'invoices' => [[
            'page' => 2, 'invoice_number' => 'SOR-71734', 'invoice_date' => '2026-09-18', 'vendor_name' => 'SORFIN YOSHIMURA, LTD.',
            'vendor_tin' => null, 'currency' => 'USD', 'po_numbers' => ['4100385065', '4100385620'], 'gr_numbers' => ['5002258048', '5002258099'],
            'lines' => [
                $line('HEAD CYLINDER (NASON CE-02189)', 'E1112099', 1, 630.00),
                $line('P/N E-10924 V-BELT PULLEY UHMW', 'E9172696', 8, $pulleyPrice),
            ],
            'charges' => [['description' => 'Inland freight and courier', 'amount' => 180]],
            'subtotal' => null, 'discount' => null, 'vat_amount' => null, 'total_amount' => 630 + 8 * $pulleyPrice + 180, 'notes' => null,
        ]],
    ];
}

function processPage(): Testable
{
    test()->actingAs(test()->vendorUser);

    return Livewire::test(BulkGoodsReceipt::class, ['ids' => [test()->cylinderState->id, test()->pulleyState->id]])
        ->set('attachments', ['attachments-pdf/invoice.pdf']);
}

function readInvoiceAction(): TestAction
{
    return TestAction::make('readInvoice')->schemaComponent('attachments', schema: 'content');
}

it('starts with an empty Inv Qty and For Verification', function () {
    $page = processPage();

    expect($page->get('goods_receipts.0.goodReceiptItems.0.quantity'))->toBeNull()
        ->and($page->get('goods_receipts.0.goodReceiptItems.0.include'))->toBeFalse()
        ->and($page->html())->toContain('For Verification');
});

it('reads the invoice and fills Inv Qty, the badges, the reference number and the date', function () {
    VendorInvoiceReader::fake([sorfinRead()])->preventStrayPrompts();

    $page = processPage()
        ->mountAction(readInvoiceAction());

    // The review (modal) shows the invoice, each GR line's match and the extra charge
    expect($page->get('mountedActions.0.data.match.invoices.0.invoice_number'))->toBe('SOR-71734')
        ->and($page->get('mountedActions.0.data.match.charges.0.description'))->toBe('Inland freight and courier')
        ->and($page->get('mountedActions.0.data.match.files.0.pages'))->toBe(['import_permit', 'invoice']);

    $page->callMountedAction()
        ->assertNotified('Invoice applied');

    expect($page->get('goods_receipts.0.goodReceiptItems.0.include'))->toBeTrue()
        ->and($page->get('goods_receipts.0.goodReceiptItems.0.quantity'))->toBe(1)
        ->and($page->get('goods_receipts.1.goodReceiptItems.0.quantity'))->toBe(8)
        ->and($page->get('goods_receipts.1.goodReceiptItems.0.ai.invoice_number'))->toBe('SOR-71734')
        ->and($page->get('reference_number'))->toBe(['SOR-71734'])
        ->and($page->get('invoiceAt'))->toBe('2026-09-18')
        ->and($page->get('invoiceMatch.charges.0.amount'))->toEqual(180)
        ->and($page->html())->toContain('Matched')->not->toContain('For Verification')
        ->and($page->instance()->matchFlags())->toBe([]);

    // Matched lines submit without a warning to AP
    $page->call('submit')->assertHasNoErrors();

    $invoice = Invoice::query()->sole();

    expect($invoice->match_flag_count)->toBe(0)
        ->and($invoice->invoice_match['charges'][0]['amount'])->toEqual(180)
        ->and($invoice->lines->every(fn ($line) => $line->is_invoice_verified && $line->match_status === P2pThreeWayMatchStatus::Matched))->toBeTrue()
        ->and($invoice->lines->firstWhere('purchase_order', '4100385620')->invoice_unit_price)->toBe('70.0000');

    VendorInvoiceReader::assertPromptedTimes(1);
});

it('lets the vendor submit a price mismatch, and warns the AP Processor', function () {
    VendorInvoiceReader::fake([sorfinRead(pulleyPrice: 72.00)]);

    $page = processPage()->callAction(readInvoiceAction());

    expect($page->instance()->matchFlags())->toHaveCount(1)
        ->and($page->instance()->matchFlags()[0]['reasons'])->toBe(['Price 72.00 is not the PO price 70.00.']);

    $page->callAction(TestAction::make('submit')->schemaComponent('formActions', schema: 'content'), data: ['match_remarks' => 'New price list from September'])
        ->assertHasNoErrors();

    $invoice = Invoice::query()->sole();
    $pulley = $invoice->lines->firstWhere('purchase_order', '4100385620');

    expect($invoice->match_flag_count)->toBe(1)
        ->and($invoice->invoice_match['vendor_remarks'])->toBe('New price list from September')
        ->and($pulley->match_status)->toBe(P2pThreeWayMatchStatus::NoMatch)
        ->and($pulley->needsMatchReview())->toBeTrue();

    $processor = User::factory()->create()->assignRole(RoleName::ApProcessor);
    $this->actingAs($processor);

    Livewire::test(BulkAccountsPayable::class, ['invoice' => $invoice->id])
        ->assertSee('1 line to check against the vendor')
        ->assertSee('Price 72.00 is not the PO price 70.00.')
        ->assertSee('New price list from September');

    Livewire::test(ListApprovals::class)
        ->filterTable('needs_invoice_review')
        ->assertCanSeeTableRecords([$invoice])
        ->assertSee('⚠ 1 to check');
});

it('flags an Inv Qty the vendor changed after the read', function () {
    VendorInvoiceReader::fake([sorfinRead()]);

    $page = processPage()
        ->callAction(readInvoiceAction())
        ->set('goods_receipts.1.goodReceiptItems.0.quantity', 6);

    expect($page->get('goods_receipts.1.goodReceiptItems.0.quantity_edited'))->toBeTrue()
        ->and($page->instance()->matchFlags()[0]['reasons'])->toBe(['Inv Qty changed by the vendor (the invoice says 8).']);
});

it('flags lines selected by hand without reading the invoice', function () {
    $page = processPage()->set('goods_receipts.0.goodReceiptItems.0.include', true);

    expect($page->get('goods_receipts.0.goodReceiptItems.0.quantity'))->toEqual(1)
        ->and($page->instance()->matchFlags()[0]['reasons'])->toBe(['Not checked against an invoice: selected by hand.']);
});

it('asks for the PDF when Read invoice is clicked without one', function () {
    VendorInvoiceReader::fake()->preventStrayPrompts();

    processPage()
        ->set('attachments', [])
        ->mountAction(readInvoiceAction())
        ->assertNotified('Upload your invoice first');

    VendorInvoiceReader::assertNeverPrompted();
});

it('shows the review with each GR line, unmatched invoice lines and charges', function () {
    $match = app(P2pInvoiceMatcher::class)->match(sorfinRead(pulleyPrice: 72.00)['invoices'], SapGoodReceiptItem::query()->with(['item', 'goodReceipt'])->get());

    $this->view('filament.p2p.invoice-match-review', [
        'match' => [...$match, 'files' => [['name' => 'SOR-71734.pdf', 'reused' => true, 'pages' => ['import_permit', 'invoice']]]],
        'rows' => [
            $this->cylinder->id => ['gr_number' => '5002258048', 'purchase_order' => '4100385065', 'purchase_order_item' => '00010', 'item_name' => 'HEAD CYLINDER NASON', 'uom' => 'EA', 'quantity_served' => 1, 'quantity_remaining' => 1],
            $this->pulley->id => ['gr_number' => '5002258099', 'purchase_order' => '4100385620', 'purchase_order_item' => '00010', 'item_name' => 'V-BELT PULLEY UHMW', 'uom' => 'EA', 'quantity_served' => 8, 'quantity_remaining' => 8],
        ],
    ])
        ->assertSee('GR Qty')
        ->assertSee('No new AI call was made')
        ->assertSeeText('SOR-71734.pdf: import permit, invoice')
        ->assertSee('Price 72.00 is not the PO price 70.00.')
        ->assertSee('No Match')
        ->assertSee('Charges not on any PO')
        ->assertSee('Inland freight and courier');
});
