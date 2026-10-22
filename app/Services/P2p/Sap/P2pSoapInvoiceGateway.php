<?php

namespace App\Services\P2p\Sap;

use App\Contracts\P2p\SapInvoiceParkingGateway;
use App\Models\P2p\Invoice;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use SimpleXMLElement;

/**
 * Ports the legacy `SapIncomingInvoiceService` SOAP write.
 *
 * RFC ZMM_FM_P2P_INCOMINGINVOICE / ACTION=PARK. The legacy built the XML by
 * string interpolation; this uses DOMDocument so values are escaped.
 */
class P2pSoapInvoiceGateway implements SapInvoiceParkingGateway
{
    private const string SOAP_ACTION = 'urn:sap-com:document:sap:rfc:functions:ZMM_FM_P2P_INCOMINGINVOICE';

    private const string SOAP_NAMESPACE = 'urn:sap-com:document:sap:rfc:functions';

    public function __construct(private readonly P2pSapClient $client) {}

    public function park(Invoice $invoice): array
    {
        $endpoint = (string) config('p2p.sap.incoming_invoice_endpoint');

        if ($endpoint === '') {
            return ['success' => false, 'error' => 'SAP incoming invoice endpoint is not configured.'];
        }

        $payload = $this->payloadFor($invoice);
        $xml = $this->buildXml($payload);
        $result = $this->client->postSoap($endpoint, $xml, self::SOAP_ACTION, $invoice->id);

        if (! $result['success']) {
            // SAP often returns a SOAP fault with a non-2xx status; surface the
            // fault message rather than the generic HTTP error.
            $parsedFault = $this->parseResponse((string) ($result['body'] ?? ''));

            return [
                'success' => false,
                'error' => $parsedFault['error'] ?? $result['error'] ?? 'SAP SOAP request failed.',
                'http_status' => $result['status'] ?? null,
            ];
        }

        $parsed = $this->parseResponse((string) ($result['body'] ?? ''));

        if (filled($parsed['error'])) {
            return [
                'success' => false,
                'error' => $parsed['error'],
                'http_status' => $result['status'] ?? null,
            ];
        }

        return [
            'success' => true,
            'invoice_number' => $parsed['invoice_number'],
            'fiscal_year' => $parsed['fiscal_year'],
            'message' => $parsed['message'],
            'http_status' => $result['status'] ?? null,
        ];
    }

    /**
     * The payload shape from the legacy `postSapInvoice()` / BulkAccountsPayable.
     *
     * @return array<string, mixed>
     */
    public function payloadFor(Invoice $invoice): array
    {
        $invoice->loadMissing('lines.sapItem.goodReceipt');

        $references = array_values(array_filter(Arr::wrap($invoice->reference_number)));
        $consolidated = [];

        foreach ($invoice->lines as $line) {
            $sapItem = $line->sapItem;
            $grNumber = $sapItem?->goodReceipt?->good_receipt_number;
            $poNumber = (string) $line->purchase_order;
            $poItem = (string) $line->purchase_order_item;
            $refDoc = $grNumber !== null ? Str::before($grNumber, '-') : null;

            $key = implode('|', [
                $poNumber,
                $poItem,
                (string) $refDoc,
                (string) ($sapItem?->material_doc_year ?? ''),
                (string) ($sapItem?->material_doc_item ?? ''),
            ]);

            if (! isset($consolidated[$key])) {
                $consolidated[$key] = [
                    'PO_NUMBER' => $poNumber,
                    'PO_ITEM' => $poItem,
                    'REF_DOC' => $refDoc,
                    'REF_DOC_YEAR' => $sapItem?->material_doc_year,
                    'REF_DOC_ITM' => $sapItem?->material_doc_item,
                    'QUANTITY' => 0.0,
                    'TAX_CODE' => $invoice->tax_code ?? 'I2',
                    'WI_TAX_CODE' => $invoice->withholding_tax_code ?? '00',
                    'WI_TAX_TYPE' => 'EI',
                    'MATERIAL' => $line->item_name ?? $sapItem?->item_name,
                    'ITEM_AMOUNT' => 0.0,
                ];
            }

            $consolidated[$key]['QUANTITY'] += (float) $line->quantity_billed;
            $consolidated[$key]['ITEM_AMOUNT'] += (float) $line->amount;
        }

        $items = array_map(static function (array $item): array {
            $item['QUANTITY'] = round((float) $item['QUANTITY'], 3);
            $item['ITEM_AMOUNT'] = round((float) $item['ITEM_AMOUNT'], 2);

            return $item;
        }, array_values($consolidated));

        $grossAmount = (float) ($invoice->total ?: $invoice->amount_submitted);

        return [
            'ACTION' => 'PARK',
            'HEADER_TEXT' => implode(', ', $references),
            'REF_DOC_NO' => implode(', ', $references),
            'GROSS_AMOUNT' => number_format($grossAmount, 2, '.', ''),
            'DOC_DATE' => ($invoice->invoice_at ?? now())->format('Y-m-d'),
            'ITEMS' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function buildXml(array $payload): string
    {
        $document = new DOMDocument('1.0', 'utf-8');
        $document->formatOutput = true;

        $envelope = $document->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soapenv:Envelope');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:urn', self::SOAP_NAMESPACE);
        $document->appendChild($envelope);

        $envelope->appendChild($document->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soapenv:Header'));

        $body = $document->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soapenv:Body');
        $envelope->appendChild($body);

        $function = $document->createElementNS(self::SOAP_NAMESPACE, 'urn:ZMM_FM_P2P_INCOMINGINVOICE');
        $body->appendChild($function);

        $header = $document->createElement('HEADER');
        $function->appendChild($header);

        foreach (['ACTION', 'HEADER_TEXT', 'REF_DOC_NO', 'GROSS_AMOUNT', 'DOC_DATE'] as $field) {
            $this->appendValue($document, $header, $field, $payload[$field] ?? '');
        }

        $itemContainer = $document->createElement('ITEM');
        $function->appendChild($itemContainer);

        foreach (($payload['ITEMS'] ?? []) as $item) {
            $itemElement = $document->createElement('item');
            $itemContainer->appendChild($itemElement);

            foreach ([
                'PO_NUMBER', 'PO_ITEM', 'REF_DOC', 'REF_DOC_YEAR', 'REF_DOC_ITM',
                'QUANTITY', 'TAX_CODE', 'WI_TAX_CODE', 'WI_TAX_TYPE', 'MATERIAL', 'ITEM_AMOUNT',
            ] as $field) {
                $this->appendValue($document, $itemElement, $field, $item[$field] ?? '');
            }
        }

        return (string) $document->saveXML();
    }

    /**
     * @return array{invoice_number: ?string, fiscal_year: ?string, message: ?string, error: ?string}
     */
    public function parseResponse(string $responseXml): array
    {
        $xml = @simplexml_load_string($responseXml);

        if ($xml === false) {
            return [
                'invoice_number' => null,
                'fiscal_year' => null,
                'message' => null,
                'error' => 'Unable to parse SAP SOAP response XML.',
            ];
        }

        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('n0', self::SOAP_NAMESPACE);

        $fault = $xml->xpath('//soap:Fault/faultstring') ?: $xml->xpath('//*[local-name()="faultstring"]');

        if (is_array($fault) && isset($fault[0])) {
            return [
                'invoice_number' => null,
                'fiscal_year' => null,
                'message' => null,
                'error' => (string) $fault[0],
            ];
        }

        return [
            'invoice_number' => $this->firstValue($xml, 'INVOICEDOCNUMBER'),
            'fiscal_year' => $this->firstValue($xml, 'FISCALYEAR'),
            'message' => $this->firstValue($xml, 'MESSAGE'),
            'error' => null,
        ];
    }

    private function appendValue(DOMDocument $document, DOMElement $parent, string $name, mixed $value): void
    {
        $element = $document->createElement($name);
        $element->appendChild($document->createTextNode($value === null ? '' : (string) $value));
        $parent->appendChild($element);
    }

    private function firstValue(SimpleXMLElement $xml, string $field): ?string
    {
        $nodes = $xml->xpath('//n0:ZMM_FM_P2P_INCOMINGINVOICEResponse/'.$field)
            ?: $xml->xpath('//*[local-name()="'.$field.'"]');

        if (! is_array($nodes) || ! isset($nodes[0])) {
            return null;
        }

        $value = trim((string) $nodes[0]);

        return $value === '' ? null : $value;
    }
}
