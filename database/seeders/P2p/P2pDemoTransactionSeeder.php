<?php

namespace Database\Seeders\P2p;

use App\Enums\P2pStatus;
use App\Models\Company;
use App\Models\P2p\ApprovalHistory;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\InvoiceLine;
use App\Models\P2p\SapGoodReceipt;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Generic P2P transaction data for the mancom demo: POs, GRs, derived GR states and
 * submissions covering every demo status. No SAP: the mirror tables are filled
 * directly to simulate a sync. Re-runnable (truncates P2P transactions first).
 *
 * See docs/p2p/10-demo-data-plan.md. Requires RoleAndUserSeeder (vendor user).
 */
class P2pDemoTransactionSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('P2pDemoTransactionSeeder creates generic demo data and must not run in production.');
        }

        $this->reset();

        $vendor = Vendor::query()->where('code', RoleAndUserSeeder::DEMO_VENDOR_CODE)->first()
            ?? Vendor::query()->firstOrFail();
        $company = Company::query()->orderBy('code')->firstOrFail();
        $submitter = User::query()->where('email', 'vendor@ramcar.com')->first()
            ?? User::query()->firstOrFail();

        DB::transaction(function () use ($vendor, $company, $submitter): void {
            $scenarios = [
                ['4500000001', '5000000001', P2pStatus::Pending, null],
                ['4500000002', '5000000002', P2pStatus::SubmittedToAp, P2pStatus::SubmittedToAp],
                ['4500000003', '5000000003', P2pStatus::SubmittedToChecker, P2pStatus::SubmittedToChecker],
                ['4500000004', '5000000004', P2pStatus::ReturnedToVendor, P2pStatus::ReturnedToVendor],
                ['4500000005', '5000000005', P2pStatus::EscalatedToWarehouse, P2pStatus::EscalatedToWarehouse],
                ['4500000006', '5000000006', P2pStatus::EscalateToProcurement, P2pStatus::EscalateToProcurement],
                ['4500000007', '5000000007', P2pStatus::PartiallyEscalated, P2pStatus::PartiallyEscalated],
                ['4500000008', '5000000008', P2pStatus::Reversed, null],
                // Extra pending GRs across POs so the vendor can demo bulk "Process Selected".
                ['4500000009', '5000000009', P2pStatus::Pending, null],
                ['4500000010', '5000000010', P2pStatus::Pending, null],
                ['4500000011', '5000000011', P2pStatus::Pending, null],
            ];

            foreach ($scenarios as $index => [$po, $gr, $stateStatus, $invoiceStatus]) {
                $this->seedScenario($vendor, $company, $submitter, $po, $gr, $stateStatus, $invoiceStatus, $index);
            }
        });
    }

    protected function seedScenario(
        Vendor $vendor,
        Company $company,
        User $submitter,
        string $poNumber,
        string $grNumber,
        P2pStatus $stateStatus,
        ?P2pStatus $invoiceStatus,
        int $index,
    ): void {
        $items = [
            ['RM-0001', 'Lead Ingot', 'KG', 'B001', 100, 85.00],
            ['SP-0001', 'Conveyor Belt', 'M', 'B002', 20, 1250.00],
        ];

        $total = 0.0;

        $purchaseOrder = SapPurchaseOrder::create([
            'company_code' => $company->code,
            'purchase_order' => $poNumber,
            'vendor_code' => $vendor->code,
            'purchase_order_date' => now()->subDays(30 - $index)->toDateString(),
            'purchase_order_type' => 'NB',
            'changed_on' => now()->subDays(3)->toDateString(),
            'created_by' => 'DEMO',
            'currency_key' => 'PHP',
            'gr_numbers' => [$grNumber],
        ]);

        $receipt = SapGoodReceipt::create([
            'purchase_order' => $poNumber,
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'good_receipt_number' => $grNumber,
            'reference_number' => 'DR-'.$grNumber,
            'date_at' => now()->subDays(5)->toDateString(),
            'upload_at' => now()->subDays(4)->toDateString(),
        ]);

        $sapItems = collect($items)->map(function (array $item, int $i) use ($receipt, $poNumber, &$total): SapGoodReceiptItem {
            [$code, $name, $uom, $sloc, $qty, $unitCost] = $item;
            $amount = round($qty * $unitCost, 2);
            $total += $amount;

            return SapGoodReceiptItem::create([
                'p2p_sap_good_receipt_id' => $receipt->id,
                'purchase_order' => $poNumber,
                'purchase_order_item' => sprintf('%05d', ($i + 1) * 10),
                'item_numbering' => 1,
                'material_doc_year' => (int) now()->format('Y'),
                'material_doc_item' => '0001',
                'storage_location' => $sloc,
                'item_name' => $name,
                'quantity_po' => $qty,
                'quantity_served' => $qty,
                'uom' => $uom,
                'unit_cost' => $unitCost,
                'amount' => $amount,
            ]);
        });

        $purchaseOrder->update(['total_amount' => $total, 'quantity_po_balance' => 0]);
        $receipt->update(['amount_total' => $total]);

        $state = GoodReceiptState::create([
            'p2p_sap_good_receipt_id' => $receipt->id,
            'purchase_order' => $poNumber,
            'good_receipt_number' => $grNumber,
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'status' => $stateStatus,
            'quantity_total' => $items[0][4] + $items[1][4],
            'quantity_served' => $invoiceStatus === null ? 0 : $items[0][4] + $items[1][4],
            'amount_total' => $total,
            'is_fully_processed' => false,
        ]);

        if ($stateStatus === P2pStatus::Reversed) {
            $state->update([
                'reversed_at' => now()->subDay(),
                'reversal_document' => '49'.sprintf('%09d', $index),
                'replacement_gr_number' => $grNumber.'-R',
            ]);
        }

        if ($invoiceStatus === null) {
            return;
        }

        $invoice = Invoice::create([
            'status' => $invoiceStatus,
            'user_id' => $submitter->id,
            'vendor_id' => $vendor->id,
            'company_id' => $company->id,
            'po_numbers' => [$poNumber],
            'gr_numbers' => [$grNumber],
            'reference_number' => ['DR-'.$grNumber],
            'invoice_number' => 'INV-'.$grNumber,
            'invoice_at' => now()->subDays(4),
            'submitted_at' => now()->subDays(3),
            'quantity_submitted' => $items[0][4] + $items[1][4],
            'amount_submitted' => $total,
            'vatable_sales' => $total,
            'total' => $total,
        ]);

        foreach ($sapItems as $sapItem) {
            InvoiceLine::create([
                'p2p_invoice_id' => $invoice->id,
                'p2p_sap_good_receipt_item_id' => $sapItem->id,
                'purchase_order' => $poNumber,
                'purchase_order_item' => $sapItem->purchase_order_item,
                'storage_location' => $sapItem->storage_location,
                'item_name' => $sapItem->item_name,
                'quantity_po' => $sapItem->quantity_po,
                'quantity_billed' => $sapItem->quantity_po,
                'quantity_remaining' => 0,
                'is_fully_billed' => true,
                'uom' => $sapItem->uom,
                'unit_cost' => $sapItem->unit_cost,
                'amount' => $sapItem->amount,
            ]);
        }

        ApprovalHistory::create([
            'p2p_invoice_id' => $invoice->id,
            'actioned_by' => $submitter->id,
            'from_status' => P2pStatus::Pending,
            'to_status' => $invoiceStatus,
            'remarks' => 'Invoice submitted by vendor.',
            'is_visible_to_vendor' => true,
        ]);
    }

    /**
     * Clear the P2P transaction tables so the seeder is re-runnable.
     */
    protected function reset(): void
    {
        DB::transaction(function (): void {
            foreach ([
                'p2p_sap_api_logs',
                'p2p_approval_histories',
                'p2p_escalations',
                'p2p_invoice_lines',
                'p2p_invoices',
                'p2p_good_receipt_states',
                'p2p_sap_good_receipt_items',
                'p2p_sap_good_receipts',
                'p2p_sap_purchase_orders',
            ] as $table) {
                DB::table($table)->delete();
            }
        });
    }
}
