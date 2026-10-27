<?php

use App\Enums\P2pStatus;
use App\Enums\P2pThreeWayMatchStatus;
use App\Models\P2p\Invoice;
use App\Models\P2p\Item;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Services\P2p\P2pInvoiceMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

pest()->use(RefreshDatabase::class);

/**
 * A GR line: [PO, GR, PO item, material code, short text, PO qty, GR qty, net price, currency].
 */
function grLine(string $po, string $gr, string $poItem, string $code, string $name, int $poQty, int $grQty, float $price, string $currency = 'PHP'): SapGoodReceiptItem
{
    $receipt = SapGoodReceipt::query()->firstOrCreate(['good_receipt_number' => $gr], SapGoodReceipt::factory()->raw(['purchase_order' => $po, 'good_receipt_number' => $gr]));

    return SapGoodReceiptItem::factory()->for($receipt, 'goodReceipt')->create([
        'purchase_order' => $po,
        'purchase_order_item' => $poItem,
        'item_id' => Item::query()->create(['code' => $code, 'name' => $name, 'is_active' => true])->id,
        'item_name' => $name,
        'quantity_po' => $poQty,
        'quantity_served' => $grQty,
        'net_price' => $price,
        'price_unit' => 1,
        'currency' => $currency,
    ]);
}

/**
 * @param  list<SapGoodReceiptItem>  $items
 * @return Collection<int, SapGoodReceiptItem>
 */
function onScreen(array $items): Collection
{
    return SapGoodReceiptItem::query()->with(['item', 'goodReceipt'])->whereKey(collect($items)->pluck('id'))->get();
}

function invoiceLine(array $overrides = []): array
{
    return array_replace([
        'description' => null, 'material_code' => null, 'po_number' => null, 'gr_number' => null,
        'quantity' => 1, 'uom' => 'EA', 'unit_price' => 1.0, 'amount' => 1.0, 'confidence' => 'high',
    ], $overrides);
}

function readInvoice(array $lines, array $overrides = []): array
{
    return array_replace([
        'page' => 1, 'invoice_number' => 'SI-1001', 'invoice_date' => '2026-09-18', 'vendor_name' => 'Vendor', 'vendor_tin' => null,
        'currency' => 'USD', 'po_numbers' => [], 'gr_numbers' => [], 'lines' => $lines, 'charges' => [],
        'subtotal' => null, 'discount' => null, 'vat_amount' => null,
        'total_amount' => collect($lines)->sum('amount'), 'notes' => null,
    ], $overrides);
}

it('links one invoice across two POs even when handwritten material codes are misread', function () {
    $cylinder = grLine('4100385065', '5002258048', '00010', 'E1112099', 'HEAD CYLINDER NASON', 1, 1, 630.00, 'USD');
    $sprocket = grLine('4100385065', '5002258048', '00020', 'E9430100', 'EZ CLEAN SPROCKET', 5, 5, 65.00, 'USD');
    $pulley = grLine('4100385620', '5002258099', '00010', 'E9172096', 'V-BELT PULLEY UHMW', 8, 8, 70.00, 'USD');

    $result = app(P2pInvoiceMatcher::class)->match([readInvoice([
        invoiceLine(['description' => 'HEAD CYLINDER (NASON CE-02189)', 'material_code' => 'E1112099', 'quantity' => 1, 'unit_price' => 630, 'amount' => 630]),
        invoiceLine(['description' => 'BLUE POLYPROPYLENE EZ CLEAN SPROCKET', 'material_code' => 'E9620100', 'quantity' => 5, 'unit_price' => 65, 'amount' => 325]),
        invoiceLine(['description' => 'P/N E-10924 V-BELT PULLEY UHMW', 'material_code' => 'E9172696', 'quantity' => 8, 'unit_price' => 70, 'amount' => 560]),
    ], [
        'po_numbers' => ['4100385065', '4100385620'],
        'gr_numbers' => ['5002258048', '5002258099'],
        'charges' => [['description' => 'Inland freight', 'amount' => 180]],
        'total_amount' => 1695,
    ])], onScreen([$cylinder, $sprocket, $pulley]));

    expect($result['rows'])->toHaveKeys([$cylinder->id, $sprocket->id, $pulley->id])
        ->and(collect($result['rows'])->pluck('status')->unique()->all())->toBe([P2pThreeWayMatchStatus::Matched->value])
        ->and($result['rows'][$sprocket->id]['quantity'])->toBe(5.0)
        ->and($result['unmatched_lines'])->toBe([])
        ->and($result['charges'])->toBe([['invoice_number' => 'SI-1001', 'description' => 'Inland freight', 'amount' => 180.0]])
        ->and($result['warnings'])->toBe([]);
});

it('flags a price one centavo off the PO price', function () {
    $fuse = grLine('4100386223', '5002259559', '00010', 'E9683647', 'FUSE 7A 600VAC', 6, 6, 503.50);

    $row = app(P2pInvoiceMatcher::class)->match([readInvoice([
        invoiceLine(['material_code' => 'E9683647', 'quantity' => 6, 'unit_price' => 503.51, 'amount' => 3021.06]),
    ], ['currency' => 'PHP', 'po_numbers' => ['4100386223']])], onScreen([$fuse]))['rows'][$fuse->id];

    expect($row['status'])->toBe(P2pThreeWayMatchStatus::NoMatch->value)
        ->and($row['reasons'])->toContain('Price 503.51 is not the PO price 503.50.');
});

it('flags an invoice in another currency than the PO', function () {
    $impeller = grLine('4100382699', '5002258944', '00010', 'E7877051', 'IMPELLER POS.90', 1, 1, 580.28, 'EUR');

    $row = app(P2pInvoiceMatcher::class)->match([readInvoice([
        invoiceLine(['material_code' => 'E7877051', 'quantity' => 1, 'unit_price' => 580.28, 'amount' => 580.28]),
    ], ['currency' => 'USD', 'po_numbers' => ['4100382699']])], onScreen([$impeller]))['rows'][$impeller->id];

    expect($row['status'])->toBe(P2pThreeWayMatchStatus::NoMatch->value)
        ->and($row['reasons'])->toContain('Currency USD is not the PO currency EUR.');
});

it('takes the PO currency when the invoice prints none, and keeps an open PO open', function () {
    $chain = grLine('4100387372', '5002261102', '00010', 'E9263012', 'ROLLER CHAIN 40-1R DONGHUA', 20, 10, 39.80);

    $row = app(P2pInvoiceMatcher::class)->match([readInvoice([
        invoiceLine(['description' => 'DONGHUA 40-1R D-HUA', 'quantity' => 10, 'uom' => 'FT/S', 'unit_price' => 39.80, 'amount' => 398]),
    ], ['currency' => null, 'po_numbers' => ['4100387372']])], onScreen([$chain]))['rows'][$chain->id];

    expect($row['status'])->toBe(P2pThreeWayMatchStatus::OpenPo->value)
        ->and($row['currency'])->toBe('PHP')
        ->and($row['reasons'])->toContain('No currency printed on the invoice: taken as the PO currency (PHP).');
});

it('flags billing more than was received, and notes a partial invoice', function () {
    $belt = grLine('4500000100', '5000000100', '00010', 'SP-0001', 'CONVEYOR BELT', 20, 20, 1250.00);
    $ingot = grLine('4500000100', '5000000100', '00020', 'RM-0001', 'LEAD INGOT', 100, 100, 85.00);

    $result = app(P2pInvoiceMatcher::class)->match([readInvoice([
        invoiceLine(['material_code' => 'SP-0001', 'quantity' => 25, 'unit_price' => 1250, 'amount' => 31250]),
        invoiceLine(['material_code' => 'RM-0001', 'quantity' => 60, 'unit_price' => 85, 'amount' => 5100]),
    ], ['currency' => 'PHP', 'po_numbers' => ['4500000100']])], onScreen([$belt, $ingot]));

    expect($result['rows'][$belt->id]['status'])->toBe(P2pThreeWayMatchStatus::NoMatch->value)
        ->and($result['rows'][$belt->id]['reasons'])->toContain('Inv Qty 25 is more than the 20 received and not yet billed.')
        ->and($result['rows'][$ingot->id]['status'])->toBe(P2pThreeWayMatchStatus::Matched->value)
        ->and($result['rows'][$ingot->id]['reasons'])->toContain('Partial: 60 of 100 billed on this invoice.');
});

it('reports lines it cannot link and invoices for POs that are not on screen', function () {
    $fuse = grLine('4100386223', '5002259559', '00010', 'E9683647', 'FUSE 7A 600VAC', 6, 6, 503.50);

    $result = app(P2pInvoiceMatcher::class)->match([readInvoice([
        invoiceLine(['description' => 'HEAD CYLINDER', 'material_code' => 'E1112099', 'quantity' => 1, 'unit_price' => 630, 'amount' => 630]),
        invoiceLine(['description' => 'FLEX HEAD', 'material_code' => 'E9172067', 'quantity' => 6, 'unit_price' => 15.5, 'amount' => 93]),
    ], ['po_numbers' => ['4100385065']])], onScreen([$fuse]));

    expect($result['rows'])->toBe([])
        ->and($result['unmatched_lines'])->toHaveCount(2)
        ->and($result['warnings'])->toContain('Invoice SI-1001 is for PO 4100385065, which is not among the GRs selected.');
});

it('warns when the totals do not add up or the invoice was already billed', function () {
    $fuse = grLine('4100386223', '5002259559', '00010', 'E9683647', 'FUSE 7A 600VAC', 6, 6, 503.50);
    Invoice::factory()->create(['status' => P2pStatus::SubmittedToAp, 'reference_number' => ['17294']]);

    $warnings = app(P2pInvoiceMatcher::class)->match([readInvoice([
        invoiceLine(['material_code' => 'E9683647', 'quantity' => 6, 'unit_price' => 503.50, 'amount' => 3021]),
    ], ['invoice_number' => '17294', 'currency' => 'PHP', 'po_numbers' => ['4100386223'], 'total_amount' => 3500])], onScreen([$fuse]))['warnings'];

    expect($warnings)->toContain('Invoice 17294 is already on another submission. Make sure it isn\'t billed twice.')
        ->toContain('Invoice 17294: the lines add up to 3,021.00, but the total is 3,500.00.');
});

it('matches an invoice line naming the base GR to an item on a split GR', function () {
    $split = grLine('4100000001', '5002258048-P1', '00010', 'E1112099', 'HEAD CYLINDER NASON', 1, 1, 630.00, 'USD');
    $decoy = grLine('4100000001', '5002258049', '00020', 'E9999999', 'OTHER ITEM', 1, 1, 10.00, 'USD');

    $result = app(P2pInvoiceMatcher::class)->match([readInvoice([
        invoiceLine([
            'description' => 'UNRELATED TEXT',
            'material_code' => 'ZZZ',
            'po_number' => '4100000001',
            'gr_number' => '5002258048',
            'quantity' => 1,
            'unit_price' => 1,
            'amount' => 1,
        ]),
    ], ['currency' => 'USD', 'po_numbers' => ['4100000001'], 'gr_numbers' => ['5002258048']])], onScreen([$split, $decoy]));

    expect($result['rows'])->toHaveKey($split->id)
        ->and($result['rows'])->not->toHaveKey($decoy->id);
});
