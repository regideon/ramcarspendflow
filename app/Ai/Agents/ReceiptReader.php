<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Reads the receipts, invoices or bills in a line's documents (PDFs or images) into RFP line
 * fields: one entry per receipt, so several receipts become several lines. The requestor
 * reviews the result before it is applied (App\Services\Rfp\ReceiptScanner).
 */
#[Provider(Lab::OpenAI)]
#[Timeout(90)]
class ReceiptReader implements Agent, HasStructuredOutput
{
    use Promptable;

    public const array CONFIDENCE = ['high', 'medium', 'low'];

    /** Bump when the instructions or schema change, so saved reads (ReceiptRead) are not reused. */
    public const int VERSION = 3;

    /**
     * @param  list<string>  $subcategories  sub category names the line may use, to suggest one from
     */
    public function __construct(public array $subcategories = []) {}

    public function model(): ?string
    {
        return config('services.receipt_reader.model') ?: null;
    }

    public function instructions(): Stringable|string
    {
        $subcategories = $this->subcategories === []
            ? 'None given: return null for suggested_subcategory.'
            : implode("\n", array_map(fn (string $name): string => "- {$name}", $this->subcategories));

        return <<<PROMPT
            You read supporting documents for a Request for Payment at a company in the Philippines:
            official receipts (OR), sales or service invoices (SI), billing statements, utility bills and
            foreign invoices. Photos may be skewed, faded thermal paper or handwritten.

            Several receipts:
            - Files are attached in order, numbered from 1. A file can hold several receipts (e.g. several
              receipts scanned into one PDF, or two receipts in one photo), and several files can be one receipt.
            - Return one entry in receipts for each separate receipt, invoice or bill, in the order they appear.
            - file: the number of the file the receipt is on. page: the PDF page it starts on (1 for an image).
            - Pages that only support a receipt (import permit, delivery receipt, packing list, PO, payment slip,
              a continuation page) are not separate receipts: count them with the receipt they belong to.
            - When unsure whether two pages are one receipt or two, return them as one and say so in notes.

            Rules (for each receipt):
            - Only return what is printed or written on the document. Never guess. Use null when a value is missing or unreadable.
            - invoice_number: the OR / SI / invoice / statement number, exactly as printed, without labels like "No.".
            - invoice_date: the document date as YYYY-MM-DD. Philippine documents usually write dates as MM/DD/YYYY.
            - vendor_name: the seller's registered name. vendor_tin: the seller's TIN (e.g. 000-123-456-00000), not the buyer's.
            - currency: ISO 4217 code (PHP for ₱, Php or P amounts).
            - total_amount: the amount due or total paid, including VAT, as a number without separators.
            - vatable_amount and vat_amount: from the VAT breakdown, when printed.
            - description: a short summary of what was bought or billed, at most 150 characters (e.g. "Office supplies: bond paper, toner").
            - purpose: one plain sentence for the request's purpose: what is being paid and for which period or use,
              at most 300 characters (e.g. "Payment for office supplies (bond paper, toner) from Alpha Office Supplies, OR 0012457.").
            - suggested_subcategory: the best match from the list below, copied exactly, or null when none fits.
            - confidence: how sure you are of each value; use "low" when it is hard to read.
            - notes: anything the requestor should check (e.g. "Two totals printed", "Handwritten amount"), or null.

            Utility and telco bills (e.g. Meralco, Maynilad, Manila Water, PLDT, Globe, Smart, Converge):
            - invoice_number: the bill, statement or SOA number; if none is printed, the account number.
            - invoice_date: the bill or statement date (not the due date, not the billing period).
            - total_amount: the Total Amount Due, including any unpaid previous balance shown in it.
            - description: provider, service, account number and billing period,
              e.g. "Meralco electricity · Acct 1234567890 · Sep 1–30, 2026".
            - purpose: e.g. "Payment of Meralco electricity bill for September 2026 (Acct 1234567890).".

            Sub categories:
            {$subcategories}
            PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'receipts' => $schema->array()->items($schema->object(fn (JsonSchema $schema): array => static::receiptSchema($schema)))->required(),
        ];
    }

    /**
     * @return array<string, Type>
     */
    protected static function receiptSchema(JsonSchema $schema): array
    {
        return [
            'file' => $schema->integer()->required(),
            'page' => $schema->integer()->nullable()->required(),
            'document_type' => $schema->string()->enum(['official_receipt', 'sales_invoice', 'service_invoice', 'billing_statement', 'utility_bill', 'other'])->required(),
            'invoice_number' => $schema->string()->nullable()->required(),
            'invoice_date' => $schema->string()->nullable()->required(),
            'vendor_name' => $schema->string()->nullable()->required(),
            'vendor_tin' => $schema->string()->nullable()->required(),
            'currency' => $schema->string()->nullable()->required(),
            'total_amount' => $schema->number()->nullable()->required(),
            'vatable_amount' => $schema->number()->nullable()->required(),
            'vat_amount' => $schema->number()->nullable()->required(),
            'description' => $schema->string()->nullable()->required(),
            'purpose' => $schema->string()->nullable()->required(),
            'suggested_subcategory' => $schema->string()->nullable()->required(),
            'confidence' => $schema->object(fn (JsonSchema $schema): array => [
                'invoice_number' => $schema->string()->enum(self::CONFIDENCE)->required(),
                'invoice_date' => $schema->string()->enum(self::CONFIDENCE)->required(),
                'vendor' => $schema->string()->enum(self::CONFIDENCE)->required(),
                'amount' => $schema->string()->enum(self::CONFIDENCE)->required(),
            ])->required(),
            'notes' => $schema->string()->nullable()->required(),
        ];
    }
}
