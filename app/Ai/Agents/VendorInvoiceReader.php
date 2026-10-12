<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Reads a vendor's P2P attachment (invoice, often with its delivery receipt, PO copy or import
 * permit) into invoices and lines. It only reads: linking lines to GR items and the 3-way
 * match are done in code (App\Services\P2p\P2pInvoiceScanner), so a saved read can be reused.
 */
#[Provider(Lab::OpenAI)]
#[Timeout(150)]
class VendorInvoiceReader implements Agent, HasStructuredOutput
{
    use Promptable;

    /** Bump when the instructions or schema change, so saved reads are not reused. */
    public const int VERSION = 1;

    public const array PAGE_TYPES = ['invoice', 'delivery_receipt', 'purchase_order', 'import_permit', 'other'];

    public function model(): ?string
    {
        return config('services.invoice_reader.model') ?: null;
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
            You read documents a vendor attached to bill Philippine Batteries, Inc. (Ramcar group) for goods
            received against purchase orders. A file often holds several documents: the invoice, a delivery
            receipt, a copy of the PO, an import permit (PEZA/customs). Scans may be skewed, stamped or
            handwritten; the buyer's receiving staff often write notes on them.

            pages: classify every page (1-based) as invoice, delivery_receipt, purchase_order, import_permit or other.

            invoices: one entry per invoice found (sales invoice, charge sales invoice, service invoice,
            commercial invoice, billing statement). Take lines and amounts ONLY from invoice pages. Never take
            totals or quantities from a delivery receipt, PO copy or import permit (an import permit lists
            values for several invoices and must not be used as an invoice).
            - Only return what is printed or written. Never guess or calculate a value. Use null when missing or unreadable.
            - invoice_number: as printed, without labels ("No.", "Nº", "MLA No.", "Invoice #").
            - invoice_date: YYYY-MM-DD. Formats seen: "September 29, 2026", "9/18/2026" (MM/DD/YYYY), "260922" (YYMMDD).
            - currency: ISO 4217 (PHP for ₱, Php, P; USD for $ or US$; EUR).
            - po_numbers: the buyer's PO numbers on the invoice ("P.O. No.", "PO#", "Customer's Order No.",
              "Your order"). Usually 10 digits starting with 41 or 45. One invoice may cover several POs
              ("4100385065 & 4100385620"): list each.
            - gr_numbers: goods receipt (material document) numbers, often handwritten as "GR#", "MD#" or "MD #":
              10 digits starting with 50. List each.
            - lines: each billed line on the invoice.
              - material_code: the buyer's material number for the line, printed or handwritten next to it
                (e.g. "E9683647"). Not the vendor's part number or HS code. Null when none.
              - po_number / gr_number: only when written on that line; otherwise null.
              - quantity, uom, unit_price, amount: as printed for the line (unit_price is the price per unit).
              - confidence: how sure you are of the line's quantity and price ("low" for hard-to-read handwriting).
            - charges: amounts on the invoice that are not goods lines (freight, packing, service charge,
              courier) with their amount. Do not repeat them in lines.
            - subtotal, discount, vat_amount, total_amount: as printed (total_amount is the total amount due).
            - notes: anything the buyer should check (e.g. "Marked PARTIAL", "Total differs from lines"), or null.
            PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        $confidence = ['high', 'medium', 'low'];

        return [
            'pages' => $schema->array()->items($schema->object(fn (JsonSchema $schema): array => [
                'page' => $schema->integer()->required(),
                'type' => $schema->string()->enum(self::PAGE_TYPES)->required(),
            ]))->required(),
            'invoices' => $schema->array()->items($schema->object(fn (JsonSchema $schema): array => [
                'page' => $schema->integer()->nullable()->required(),
                'invoice_number' => $schema->string()->nullable()->required(),
                'invoice_date' => $schema->string()->nullable()->required(),
                'vendor_name' => $schema->string()->nullable()->required(),
                'vendor_tin' => $schema->string()->nullable()->required(),
                'currency' => $schema->string()->nullable()->required(),
                'po_numbers' => $schema->array()->items($schema->string())->required(),
                'gr_numbers' => $schema->array()->items($schema->string())->required(),
                'lines' => $schema->array()->items($schema->object(fn (JsonSchema $schema): array => [
                    'description' => $schema->string()->nullable()->required(),
                    'material_code' => $schema->string()->nullable()->required(),
                    'po_number' => $schema->string()->nullable()->required(),
                    'gr_number' => $schema->string()->nullable()->required(),
                    'quantity' => $schema->number()->nullable()->required(),
                    'uom' => $schema->string()->nullable()->required(),
                    'unit_price' => $schema->number()->nullable()->required(),
                    'amount' => $schema->number()->nullable()->required(),
                    'confidence' => $schema->string()->enum($confidence)->required(),
                ]))->required(),
                'charges' => $schema->array()->items($schema->object(fn (JsonSchema $schema): array => [
                    'description' => $schema->string()->nullable()->required(),
                    'amount' => $schema->number()->nullable()->required(),
                ]))->required(),
                'subtotal' => $schema->number()->nullable()->required(),
                'discount' => $schema->number()->nullable()->required(),
                'vat_amount' => $schema->number()->nullable()->required(),
                'total_amount' => $schema->number()->nullable()->required(),
                'notes' => $schema->string()->nullable()->required(),
            ]))->required(),
        ];
    }
}
