<?php

namespace Database\Seeders\P2p;

use App\Enums\P2pStatus;
use App\Models\Company;
use App\Models\P2p\ApprovalHistory;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\InvoiceLine;
use App\Models\P2p\Item;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;



// sample

/**
 * One AP approval (submission) ready to process: a SAP PO/GR mirror whose quantities
 * and prices agree with the invoice lines, plus an attached sample invoice PDF whose
 * content repeats the same information, so the Approvals queue and the AP processing
 * screen can be demoed end to end (open the PDF, check the lines and tax, then submit).
 *
 * Re-runnable: it clears and recreates only its own sample records. Requires the P2P
 * master data (a company, the demo vendor user, tax codes). See docs/p2p/10-demo-data-plan.md.
 */
class P2pApprovalSampleSeeder extends Seeder
{
    public const string PO_NUMBER = '4500099001';

    public const string GR_NUMBER = '5000099001';

    public const string INVOICE_NUMBER = 'INV-SAMPLE-0001';

    public const string DR_NUMBER = 'DR-SAMPLE-0001';

    public const string CURRENCY = 'PHP';

    public const string TAX_CODE = 'I2';

    public const float VAT_RATE = 0.12;

    /**
     * The billed lines: [item code, description, uom, storage location, quantity, unit price].
     */
    public const array LINES = [
        ['RM-0001', 'Lead Ingot', 'KG', 'B001', 100, 85.00],
        ['SP-0001', 'Conveyor Belt', 'M', 'B002', 20, 1250.00],
        ['SP-0002', 'Hydraulic Hose 1/2 in', 'M', 'B002', 15, 320.00],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('P2pApprovalSampleSeeder creates demo data and must not run in production.');
        }

        $vendor = Vendor::query()->where('code', RoleAndUserSeeder::DEMO_VENDOR_CODE)->first()
            ?? Vendor::query()->firstOrFail();
        $company = Company::query()->orderBy('code')->firstOrFail();
        $submitter = User::query()->where('email', 'vendor@ramcar.com')->first()
            ?? User::query()->firstOrFail();

        $this->purge();

        DB::transaction(function () use ($vendor, $company, $submitter): void {
            $receipt = SapGoodReceipt::create([
                'purchase_order' => self::PO_NUMBER,
                'company_id' => $company->id,
                'vendor_id' => $vendor->id,
                'good_receipt_number' => self::GR_NUMBER,
                'reference_number' => self::DR_NUMBER,
                'date_at' => now()->subDays(6)->toDateString(),
                'upload_at' => now()->subDays(5)->toDateString(),
            ]);

            $rows = collect(self::LINES)->map(function (array $line, int $index) use ($receipt): array {
                [$code, $name, $uom, $sloc, $quantity, $unitPrice] = $line;
                $amount = round($quantity * $unitPrice, 2);

                $material = Item::query()->firstOrCreate(
                    ['code' => $code],
                    ['name' => $name, 'uom' => $uom, 'unit_cost' => $unitPrice, 'is_active' => true],
                );

                $item = SapGoodReceiptItem::create([
                    'p2p_sap_good_receipt_id' => $receipt->id,
                    'purchase_order' => self::PO_NUMBER,
                    'purchase_order_item' => sprintf('%05d', ($index + 1) * 10),
                    'item_numbering' => $index + 1,
                    'item_id' => $material->id,
                    'material_doc_year' => (int) now()->format('Y'),
                    'material_doc_item' => sprintf('%04d', $index + 1),
                    'storage_location' => $sloc,
                    'item_name' => $name,
                    'quantity_po' => $quantity,
                    'quantity_served' => $quantity,
                    'uom' => $uom,
                    'unit_cost' => $unitPrice,
                    'net_price' => $unitPrice,
                    'price_unit' => 1,
                    'currency' => self::CURRENCY,
                    'amount' => $amount,
                    'quantity_po_balance' => 0,
                ]);

                return [
                    'item' => $item,
                    'code' => $code,
                    'name' => $name,
                    'uom' => $uom,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'amount' => $amount,
                ];
            });

            $subtotal = round((float) $rows->sum('amount'), 2);
            $vat = round($subtotal * self::VAT_RATE, 2);
            $total = round($subtotal + $vat, 2);
            $quantity = (int) $rows->sum('quantity');

            $receipt->update(['amount_total' => $total]);

            SapPurchaseOrder::create([
                'company_code' => $company->code,
                'purchase_order' => self::PO_NUMBER,
                'vendor_code' => $vendor->code,
                'purchase_order_date' => now()->subDays(20)->toDateString(),
                'purchase_order_type' => 'NB',
                'changed_on' => now()->subDays(3)->toDateString(),
                'created_by' => 'SAMPLE',
                'currency_key' => self::CURRENCY,
                'gr_numbers' => [self::GR_NUMBER],
                'total_amount' => $total,
                'quantity_po_balance' => 0,
            ]);

            GoodReceiptState::create([
                'p2p_sap_good_receipt_id' => $receipt->id,
                'purchase_order' => self::PO_NUMBER,
                'good_receipt_number' => self::GR_NUMBER,
                'company_id' => $company->id,
                'vendor_id' => $vendor->id,
                'status' => P2pStatus::SubmittedToAp,
                'quantity_total' => $quantity,
                'quantity_served' => $quantity,
                'amount_total' => $total,
                'is_fully_processed' => false,
            ]);

            $attachments = ['attachments-pdf/'.self::INVOICE_NUMBER.'.pdf'];

            $invoice = Invoice::create([
                'status' => P2pStatus::SubmittedToAp,
                'user_id' => $submitter->id,
                'vendor_id' => $vendor->id,
                'company_id' => $company->id,
                'po_numbers' => [self::PO_NUMBER],
                'gr_numbers' => [self::GR_NUMBER],
                'reference_number' => [self::DR_NUMBER],
                'invoice_number' => self::INVOICE_NUMBER,
                'invoice_at' => now()->subDays(2),
                'submitted_at' => now()->subDay(),
                'upload_at' => now()->subDay()->toDateString(),
                'tax_code' => self::TAX_CODE,
                'quantity_submitted' => $quantity,
                'amount_submitted' => $total,
                'vatable_sales' => $subtotal,
                'vat' => $vat,
                'total' => $total,
                'net' => $total,
                'attachments' => $attachments,
            ]);

            foreach ($rows as $row) {
                InvoiceLine::create([
                    'p2p_invoice_id' => $invoice->id,
                    'p2p_sap_good_receipt_item_id' => $row['item']->id,
                    'purchase_order' => self::PO_NUMBER,
                    'purchase_order_item' => $row['item']->purchase_order_item,
                    'item_id' => $row['item']->item_id,
                    'storage_location' => $row['item']->storage_location,
                    'item_name' => $row['name'],
                    'quantity_po' => $row['quantity'],
                    'quantity_billed' => $row['quantity'],
                    'quantity_remaining' => 0,
                    'is_fully_billed' => true,
                    'uom' => $row['uom'],
                    'unit_cost' => $row['unit_price'],
                    'amount' => $row['amount'],
                ]);
            }

            ApprovalHistory::create([
                'p2p_invoice_id' => $invoice->id,
                'actioned_by' => $submitter->id,
                'from_status' => P2pStatus::Pending,
                'to_status' => P2pStatus::SubmittedToAp,
                'remarks' => 'Sample invoice submitted by the vendor for the AP approvals demo.',
                'is_visible_to_vendor' => true,
            ]);

            $this->writeInvoicePdf($invoice, $vendor, $company, $rows->all(), $subtotal, $vat, $total);
        });

        $this->command?->info(sprintf(
            'Seeded sample AP approval %s (%s / %s) with PDF %s.',
            self::INVOICE_NUMBER,
            self::PO_NUMBER,
            self::GR_NUMBER,
            'attachments-pdf/'.self::INVOICE_NUMBER.'.pdf',
        ));
    }

    /**
     * Clear this seeder's own sample records so it can be re-run.
     */
    protected function purge(): void
    {
        DB::transaction(function (): void {
            $invoiceIds = DB::table('p2p_invoices')->where('invoice_number', self::INVOICE_NUMBER)->pluck('id');

            DB::table('p2p_approval_histories')->whereIn('p2p_invoice_id', $invoiceIds)->delete();
            DB::table('p2p_invoice_lines')->whereIn('p2p_invoice_id', $invoiceIds)->delete();
            DB::table('p2p_invoices')->whereIn('id', $invoiceIds)->delete();

            $receiptIds = DB::table('p2p_sap_good_receipts')->where('good_receipt_number', self::GR_NUMBER)->pluck('id');

            DB::table('p2p_good_receipt_states')->where('good_receipt_number', self::GR_NUMBER)->delete();
            DB::table('p2p_sap_good_receipt_items')->where('purchase_order', self::PO_NUMBER)->delete();
            DB::table('p2p_sap_good_receipts')->whereIn('id', $receiptIds)->delete();
            DB::table('p2p_sap_purchase_orders')->where('purchase_order', self::PO_NUMBER)->delete();
        });
    }

    /**
     * Write a minimal, self-contained invoice PDF that repeats the seeded information.
     *
     * @param  array<int, array{item: SapGoodReceiptItem, code: string, name: string, uom: string, quantity: int, unit_price: float, amount: float}>  $rows
     */
    protected function writeInvoicePdf(
        Invoice $invoice,
        Vendor $vendor,
        Company $company,
        array $rows,
        float $subtotal,
        float $vat,
        float $total,
    ): void {
        $rule = str_repeat('-', 88);

        $lines = [
            'RAMCAR SPEND FLOW',
            'SAMPLE VENDOR INVOICE',
            '',
            'Vendor      : '.$vendor->name.' ('.$vendor->code.')',
            'Company     : '.$company->name.' ('.$company->code.')',
            'Invoice No. : '.self::INVOICE_NUMBER,
            'Invoice Date: '.$invoice->invoice_at?->toDateString(),
            'PO No.      : '.self::PO_NUMBER,
            'GR No.      : '.self::GR_NUMBER,
            'DR No.      : '.self::DR_NUMBER,
            '',
            sprintf('%-9s %-30s %6s %-4s %15s %15s', 'Code', 'Description', 'Qty', 'UOM', 'Unit Price', 'Amount'),
            $rule,
        ];

        foreach ($rows as $row) {
            $lines[] = sprintf(
                '%-9s %-30s %6s %-4s %15s %15s',
                $row['code'],
                Str::limit($row['name'], 30, ''),
                $row['quantity'],
                $row['uom'],
                number_format($row['unit_price'], 2),
                number_format($row['amount'], 2),
            );
        }

        $lines[] = $rule;
        $lines[] = sprintf('%-48s %15s', 'Vatable Sales', number_format($subtotal, 2));
        $lines[] = sprintf('%-48s %15s', 'VAT (12%)', number_format($vat, 2));
        $lines[] = sprintf('%-48s %15s', 'Total', number_format($total, 2));
        $lines[] = '';
        $lines[] = 'System-generated sample: the lines, PO, GR and totals above match the invoice on file.';

        Storage::disk('public')->put(
            'attachments-pdf/'.self::INVOICE_NUMBER.'.pdf',
            $this->buildPdf($lines),
        );
    }

    /**
     * Build a single-page PDF (Helvetica) from plain text lines.
     *
     * @param  array<int, string>  $lines
     */
    protected function buildPdf(array $lines): string
    {
        $content = "BT\n/F1 14 Tf\n50 792 Td\n(".$this->escape($lines[0]).") Tj\n/F1 10 Tf\n0 -26 Td\n";

        foreach (array_slice($lines, 1) as $line) {
            $content .= '('.$this->escape($line).") Tj\n0 -14 Td\n";
        }

        $content .= 'ET';

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $body) {
            $number = $index + 1;
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$body."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n".'0 '.(count($objects) + 1)."\n".'0000000000 65535 f '."\n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= 'trailer'."\n".'<< /Size '.(count($objects) + 1).' /Root 1 0 R >>'."\n".'startxref'."\n".$xref."\n".'%%EOF'."\n";

        return $pdf;
    }

    /**
     * Escape a line as a PDF literal string (ASCII only).
     */
    protected function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], Str::ascii($text));
    }
}
