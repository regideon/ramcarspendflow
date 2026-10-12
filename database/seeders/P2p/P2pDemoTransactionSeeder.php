<?php

namespace Database\Seeders\P2p;

use App\Enums\P2pStatus;
use App\Models\Company;
use App\Models\P2p\ApprovalHistory;
use App\Models\P2p\Escalation;
use App\Models\P2p\EscalationReason;
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
use Illuminate\Support\Collection;
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
                ['4500000007', '5000000007', P2pStatus::EscalateToProcurement, P2pStatus::PartiallyEscalated],
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

        $receipts = [$this->seedReceipt($purchaseOrder, $vendor, $company, $grNumber, $stateStatus, $index, $invoiceStatus !== null)];

        // A partially escalated header spans several GRs sitting on different hops:
        // the demo request is raised at the procurement hop, with a second GR under
        // the same PO sitting at the warehouse hop.
        if ($invoiceStatus === P2pStatus::PartiallyEscalated) {
            $receipts[] = $this->seedReceipt(
                $purchaseOrder,
                $vendor,
                $company,
                $grNumber.'-P1',
                P2pStatus::EscalatedToWarehouse,
                $index + 100,
                true,
            );
        }

        $grNumbers = array_map(fn (array $receipt): string => $receipt['state']->good_receipt_number, $receipts);
        $total = collect($receipts)->sum('total');
        $quantity = collect($receipts)->sum('quantity');

        $purchaseOrder->update([
            'total_amount' => $total,
            'quantity_po_balance' => 0,
            'gr_numbers' => $grNumbers,
        ]);

        if ($invoiceStatus === null) {
            return;
        }

        $invoice = Invoice::create([
            'status' => $invoiceStatus,
            'user_id' => $submitter->id,
            'vendor_id' => $vendor->id,
            'company_id' => $company->id,
            'po_numbers' => [$poNumber],
            'gr_numbers' => $grNumbers,
            'reference_number' => array_map(fn (string $number): string => 'DR-'.$number, $grNumbers),
            'invoice_number' => 'INV-'.$grNumber,
            'invoice_at' => now()->subDays(4),
            'submitted_at' => now()->subDays(3),
            'quantity_submitted' => $quantity,
            'amount_submitted' => $total,
            'vatable_sales' => $total,
            'total' => $total,
        ]);

        foreach ($receipts as $receipt) {
            foreach ($receipt['items'] as $sapItem) {
                InvoiceLine::create([
                    'p2p_invoice_id' => $invoice->id,
                    'p2p_sap_good_receipt_item_id' => $sapItem->id,
                    'purchase_order' => $sapItem->purchase_order,
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
        }

        ApprovalHistory::create([
            'p2p_invoice_id' => $invoice->id,
            'actioned_by' => $submitter->id,
            'from_status' => P2pStatus::Pending,
            'to_status' => $invoiceStatus,
            'remarks' => 'Invoice submitted by vendor.',
            'is_visible_to_vendor' => true,
        ]);

        $this->seedEscalations($invoice, $receipts, $submitter);
    }

    /**
     * One mirror GR under an existing PO, with its derived state.
     *
     * @return array{state: GoodReceiptState, items: Collection<int, SapGoodReceiptItem>, quantity: int, total: float}
     */
    protected function seedReceipt(
        SapPurchaseOrder $purchaseOrder,
        Vendor $vendor,
        Company $company,
        string $grNumber,
        P2pStatus $stateStatus,
        int $index,
        bool $served,
    ): array {
        $items = [
            ['RM-0001', 'Lead Ingot', 'KG', 'B001', 100, 85.00],
            ['SP-0001', 'Conveyor Belt', 'M', 'B002', 20, 1250.00],
        ];

        $receipt = SapGoodReceipt::create([
            'purchase_order' => $purchaseOrder->purchase_order,
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'good_receipt_number' => $grNumber,
            'reference_number' => 'DR-'.$grNumber,
            'date_at' => now()->subDays(5)->toDateString(),
            'upload_at' => now()->subDays(4)->toDateString(),
        ]);

        $total = 0.0;
        $quantity = 0;

        $sapItems = collect($items)->map(function (array $item, int $i) use ($receipt, $purchaseOrder, &$total, &$quantity): SapGoodReceiptItem {
            [$code, $name, $uom, $sloc, $qty, $unitCost] = $item;
            $amount = round($qty * $unitCost, 2);
            $total += $amount;
            $quantity += $qty;

            return SapGoodReceiptItem::create([
                'p2p_sap_good_receipt_id' => $receipt->id,
                'purchase_order' => $purchaseOrder->purchase_order,
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

        $receipt->update(['amount_total' => $total]);

        $state = GoodReceiptState::create([
            'p2p_sap_good_receipt_id' => $receipt->id,
            'purchase_order' => $purchaseOrder->purchase_order,
            'good_receipt_number' => $grNumber,
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'status' => $stateStatus,
            'quantity_total' => $quantity,
            'quantity_served' => $served ? $quantity : 0,
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

        return ['state' => $state, 'items' => $sapItems, 'quantity' => $quantity, 'total' => $total];
    }

    /**
     * Open escalations for an escalated demo invoice, so the Escalations queue and
     * the workflow stepper show real records (mirroring the status a hop would have
     * produced).
     *
     * @param  array<int, array{state: GoodReceiptState, items: Collection<int, SapGoodReceiptItem>, quantity: int, total: float}>  $receipts
     */
    protected function seedEscalations(Invoice $invoice, array $receipts, User $submitter): void
    {
        $escalated = collect($receipts)
            ->filter(fn (array $receipt): bool => $this->escalationHopFor($receipt['state']->status) !== null)
            ->values();

        if ($escalated->isEmpty()) {
            return;
        }

        $escalated->each(function (array $receipt) use ($invoice, $submitter): void {
            $hop = $this->escalationHopFor($receipt['state']->status);

            if ($hop === null) {
                return;
            }

            [$reasonCode, $role] = $hop;

            $reason = EscalationReason::query()->where('code', $reasonCode)->firstOrFail();

            Escalation::query()->create([
                'escalation_number' => 'ESC-'.now()->format('Ymd').'-'.$invoice->id.'-'.$receipt['state']->id,
                // Each demo escalation is its own request (one reason/route), so it
                // gets its own batch number for a distinct stepper title.
                'batch_number' => 'BATCH-'.now()->format('Ymd').'-'.$invoice->id.'-'.$receipt['state']->id,
                'p2p_invoice_id' => $invoice->id,
                'p2p_good_receipt_state_id' => $receipt['state']->id,
                'p2p_escalation_reason_id' => $reason->id,
                'current_step_order' => 1,
                'current_role' => $role,
                'origin_role' => $reason->origin_role,
                'status' => Escalation::STATUS_OPEN,
                'from_status' => P2pStatus::SubmittedToAp,
                'remarks' => 'Demo escalation: '.$reason->name.'.',
                'actioned_by' => $submitter->id,
                'escalated_at' => now(),
            ]);
        });
    }

    /**
     * The escalation reason and current hop a GR status represents, if any.
     *
     * @return array{0: string, 1: string}|null [reason code, current hop role]
     */
    protected function escalationHopFor(P2pStatus $status): ?array
    {
        return match ($status) {
            P2pStatus::EscalatedToWarehouse => ['quantity_difference', 'warehouse'],
            P2pStatus::EscalateToProcurement => ['wrong_item_description', 'procurement'],
            default => null,
        };
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
