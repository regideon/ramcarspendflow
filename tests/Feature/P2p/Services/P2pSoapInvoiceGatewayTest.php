<?php

use App\Models\P2p\Invoice;
use App\Models\P2p\InvoiceLine;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Services\P2p\Sap\P2pSoapInvoiceGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

pest()->use(RefreshDatabase::class);

function soapInvoiceFixture(): Invoice
{
    $receipt = SapGoodReceipt::factory()->create(['good_receipt_number' => '5000000500']);

    $item = SapGoodReceiptItem::factory()->for($receipt, 'goodReceipt')->create([
        'purchase_order' => '4500000500',
        'purchase_order_item' => '00010',
        'material_doc_year' => 2026,
        'material_doc_item' => '0001',
        'item_name' => 'Widget & <Co>',
    ]);

    $invoice = Invoice::factory()->create([
        'reference_number' => ['DR-1001'],
        'invoice_at' => now()->setDate(2026, 10, 20),
        'tax_code' => 'I2',
        'withholding_tax_code' => '04',
        'total' => 1120.00,
        'amount_submitted' => 1000.00,
    ]);

    InvoiceLine::factory()->for($invoice, 'invoice')->create([
        'p2p_sap_good_receipt_item_id' => $item->id,
        'purchase_order' => '4500000500',
        'purchase_order_item' => '00010',
        'quantity_billed' => 2,
        'amount' => 200.00,
        'item_name' => 'Widget & <Co>',
    ]);

    return $invoice;
}

function soapSuccessXml(): string
{
    return <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:n0="urn:sap-com:document:sap:rfc:functions">
    <soapenv:Body>
        <n0:ZMM_FM_P2P_INCOMINGINVOICEResponse>
            <INVOICEDOCNUMBER>5100000123</INVOICEDOCNUMBER>
            <FISCALYEAR>2026</FISCALYEAR>
            <MESSAGE>Invoice parked</MESSAGE>
        </n0:ZMM_FM_P2P_INCOMINGINVOICEResponse>
    </soapenv:Body>
</soapenv:Envelope>
XML;
}

it('builds the legacy SOAP payload from the invoice lines', function () {
    $invoice = soapInvoiceFixture();
    $gateway = app(P2pSoapInvoiceGateway::class);

    $payload = $gateway->payloadFor($invoice);

    expect($payload['ACTION'])->toBe('PARK')
        ->and($payload['HEADER_TEXT'])->toBe('DR-1001')
        ->and($payload['REF_DOC_NO'])->toBe('DR-1001')
        ->and($payload['GROSS_AMOUNT'])->toBe('1120.00')
        ->and($payload['DOC_DATE'])->toBe('2026-10-20')
        ->and($payload['ITEMS'])->toHaveCount(1)
        ->and($payload['ITEMS'][0]['PO_NUMBER'])->toBe('4500000500')
        ->and($payload['ITEMS'][0]['PO_ITEM'])->toBe('00010')
        ->and($payload['ITEMS'][0]['REF_DOC'])->toBe('5000000500')
        ->and($payload['ITEMS'][0]['REF_DOC_YEAR'])->toBe(2026)
        ->and($payload['ITEMS'][0]['REF_DOC_ITM'])->toBe('0001')
        ->and($payload['ITEMS'][0]['QUANTITY'])->toBe(2.0)
        ->and($payload['ITEMS'][0]['ITEM_AMOUNT'])->toBe(200.0)
        ->and($payload['ITEMS'][0]['TAX_CODE'])->toBe('I2')
        ->and($payload['ITEMS'][0]['WI_TAX_CODE'])->toBe('04');
});

it('escapes values in the SOAP envelope', function () {
    $invoice = soapInvoiceFixture();
    $gateway = app(P2pSoapInvoiceGateway::class);

    $xml = $gateway->buildXml($gateway->payloadFor($invoice));

    expect($xml)
        ->toContain('<PO_NUMBER>4500000500</PO_NUMBER>')
        ->toContain('Widget &amp; &lt;Co&gt;')
        ->not->toContain('Widget & <Co>');
});

it('parses the SAP document number and fiscal year from a SOAP response', function () {
    $gateway = app(P2pSoapInvoiceGateway::class);

    $parsed = $gateway->parseResponse(soapSuccessXml());

    expect($parsed['invoice_number'])->toBe('5100000123')
        ->and($parsed['fiscal_year'])->toBe('2026')
        ->and($parsed['message'])->toBe('Invoice parked')
        ->and($parsed['error'])->toBeNull();
});

it('returns the SAP fault message when the SOAP call fails', function () {
    $invoice = soapInvoiceFixture();

    config([
        'p2p.sap.enabled' => true,
        'p2p.sap.incoming_invoice_endpoint' => 'https://sap.test/soap',
        'p2p.sap.incoming_invoice_username' => 'soap-user',
        'p2p.sap.incoming_invoice_password' => 'soap-secret',
        'p2p.sap.verify_tls' => false,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://sap.test/soap' => Http::response(<<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
    <soapenv:Body>
        <soapenv:Fault>
            <faultcode>soapenv:Server</faultcode>
            <faultstring>Vendor not found</faultstring>
        </soapenv:Fault>
    </soapenv:Body>
</soapenv:Envelope>
XML, 500),
    ]);

    $result = app(P2pSoapInvoiceGateway::class)->park($invoice);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('Vendor not found');
});

it('parks an invoice and returns the SAP document number', function () {
    $invoice = soapInvoiceFixture();

    config([
        'p2p.sap.enabled' => true,
        'p2p.sap.incoming_invoice_endpoint' => 'https://sap.test/soap',
        'p2p.sap.incoming_invoice_username' => 'soap-user',
        'p2p.sap.incoming_invoice_password' => 'soap-secret',
        'p2p.sap.verify_tls' => false,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://sap.test/soap' => Http::response(soapSuccessXml(), 200),
    ]);

    $result = app(P2pSoapInvoiceGateway::class)->park($invoice);

    expect($result['success'])->toBeTrue()
        ->and($result['invoice_number'])->toBe('5100000123')
        ->and($result['fiscal_year'])->toBe('2026')
        ->and($result['message'])->toBe('Invoice parked');
});

it('sends the base goods-receipt number when the line lives on a split GR', function () {
    $invoice = soapInvoiceFixture();
    $invoice->lines()->first()->sapItem->goodReceipt
        ->forceFill(['good_receipt_number' => '5000000500-P1'])
        ->save();

    $payload = app(P2pSoapInvoiceGateway::class)->payloadFor($invoice->fresh());

    expect($payload['ITEMS'][0]['REF_DOC'])->toBe('5000000500');
});
