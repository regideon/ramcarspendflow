<?php

use App\Ai\Agents\VendorInvoiceReader;
use App\Models\P2p\InvoiceRead;
use App\Services\P2p\P2pInvoiceScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

pest()->use(RefreshDatabase::class);

function vendorInvoiceRead(): array
{
    return [
        'pages' => [['page' => 1, 'type' => 'import_permit'], ['page' => 2, 'type' => 'invoice']],
        'invoices' => [[
            'page' => 2, 'invoice_number' => 'F4636', 'invoice_date' => '2026-09-22', 'vendor_name' => 'Kallstrom Engineering Systems AB',
            'vendor_tin' => null, 'currency' => 'EUR', 'po_numbers' => ['4100382699'], 'gr_numbers' => ['5002258944'],
            'lines' => [[
                'description' => 'Impeller pos.90', 'material_code' => 'E7877051', 'po_number' => null, 'gr_number' => null,
                'quantity' => 1, 'uom' => 'Each', 'unit_price' => 580.28, 'amount' => 580.28, 'confidence' => 'high',
            ]],
            'charges' => [], 'subtotal' => 580.28, 'discount' => null, 'vat_amount' => null, 'total_amount' => 580.28, 'notes' => null,
        ]],
    ];
}

it('reads a vendor PDF once and reuses the saved read', function () {
    VendorInvoiceReader::fake([vendorInvoiceRead()])->preventStrayPrompts();
    $pdf = UploadedFile::fake()->createWithContent('invoice.pdf', '%PDF-1.4 sample invoice');

    $scanner = app(P2pInvoiceScanner::class);
    $first = $scanner->readOrReuse($pdf->getRealPath());
    $second = $scanner->readOrReuse($pdf->getRealPath());

    expect($first['reused'])->toBeFalse()
        ->and($second['reused'])->toBeTrue()
        ->and($second['result']['invoices'][0]['lines'][0]['material_code'])->toBe('E7877051')
        ->and(InvoiceRead::count())->toBe(1);

    VendorInvoiceReader::assertPromptedTimes(1);
});
