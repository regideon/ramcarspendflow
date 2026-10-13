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
use App\Models\P2p\Item;
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

            // Pending GRs shaped like real vendor invoices, to try "Read invoice with AI" end to end
            // (see docs/p2p: JMA, United Bearing, Kallstrom in EUR, Sorfin in USD over two POs).
            foreach (self::INVOICE_SAMPLES as $index => [$po, $gr, $currency, $items]) {
                $this->seedScenario($vendor, $company, $submitter, $po, $gr, P2pStatus::Pending, null, 20 + $index, $items, $currency);
            }
        });
    }

    /**
     * Default lines: [material, short text, uom, sloc, PO qty, GR qty, net price].
     */
    public const array DEFAULT_ITEMS = [
        ['RM-0001', 'Lead Ingot', 'KG', 'B001', 100, 100, 85.00],
        ['SP-0001', 'Conveyor Belt', 'M', 'B002', 20, 20, 1250.00],
    ];

    /**
     * [PO, GR, currency, lines] for the sample vendor invoices.
     */
    public const array INVOICE_SAMPLES = [
        ['4100386223', '5002259559', 'PHP', [
            ['E9683647', 'FUSE,7A 600VAC BUSSMAN KTK-R-7 LIMITRON#', 'PC', 'B003', 6, 6, 503.50],
        ]],
        ['4100387372', '5002261102', 'PHP', [
            ['E9263012', 'ROLLER CHAIN 40-1R DONGHUA', 'FT', 'B003', 20, 10, 39.80],
        ]],
        ['4100382699', '5002258944', 'EUR', [
            ['E7877051', 'IMPELLER POS.90 700617-0090', 'EA', 'B004', 1, 1, 580.28],
        ]],
        ['4100385065', '5002258048', 'USD', [
            ['E1112099', 'HEAD CYLINDER NASON CE-02189', 'EA', 'B004', 1, 1, 630.00],
            ['E9182014', 'OXYGEN GAUGE PANEL MOUNT 1320', 'EA', 'B004', 1, 1, 110.00],
            ['E9430100', 'EZ CLEAN SPROCKET 40MM SQ BORE', 'EA', 'B004', 5, 5, 65.00],
        ]],
        ['4100385620', '5002258099', 'USD', [
            ['E9172096', 'V-BELT PULLEY UHMW E-10924', 'EA', 'B004', 8, 8, 70.00],
            ['E9172067', 'FLEX HEAD STANDARD E-949', 'EA', 'B004', 6, 6, 15.50],
        ]],
    ];

    /**
     * Demo pesos per unit of foreign currency, for the GR value (unit_cost) of foreign POs.
     */
    public const array DEMO_RATES = ['USD' => 58.25, 'EUR' => 63.10];

    protected function seedScenario(
        Vendor $vendor,
        Company $company,
        User $submitter,
        string $poNumber,
        string $grNumber,
        P2pStatus $stateStatus,
        ?P2pStatus $invoiceStatus,
        int $index,
        array $items = self::DEFAULT_ITEMS,
        string $currency = 'PHP',
    ): void {
        $purchaseOrder = SapPurchaseOrder::create([
            'company_code' => $company->code,
            'purchase_order' => $poNumber,
            'vendor_code' => $vendor->code,
            'purchase_order_date' => now()->subDays(30 - $index)->toDateString(),
            'purchase_order_type' => 'NB',
            'changed_on' => now()->subDays(3)->toDateString(),
            'created_by' => 'DEMO',
            'currency_key' => $currency,
            'gr_numbers' => [$grNumber],
        ]);

        $receipts = [$this->seedReceipt($purchaseOrder, $vendor, $company, $grNumber, $stateStatus, $index, $invoiceStatus !== null, $items, $currency)];

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
                $items,
                $currency,
            );
        }

        $grNumbers = array_map(fn (array $receipt): string => $receipt['state']->good_receipt_number, $receipts);
        $total = collect($receipts)->sum('total');
        $quantity = collect($receipts)->sum('quantity');

        $purchaseOrder->update([
            'total_amount' => $total,
            // Open qty (e.g. a partial delivery): PO qty not yet served.
            'quantity_po_balance' => collect($receipts)->sum('open'),
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
     * @param  array<int, array{0: string, 1: string, 2: string, 3: string, 4: int, 5: int, 6: float}>  $items
     * @return array{state: GoodReceiptState, items: Collection<int, SapGoodReceiptItem>, quantity: int, open: int, total: float}
     */
    protected function seedReceipt(
        SapPurchaseOrder $purchaseOrder,
        Vendor $vendor,
        Company $company,
        string $grNumber,
        P2pStatus $stateStatus,
        int $index,
        bool $served,
        array $items = self::DEFAULT_ITEMS,
        string $currency = 'PHP',
    ): array {
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
        $grQuantity = array_sum(array_column($items, 5));

        $sapItems = collect($items)->map(function (array $item, int $i) use ($receipt, $purchaseOrder, $currency, &$total, &$quantity): SapGoodReceiptItem {
            [$code, $name, $uom, $sloc, $poQty, $qty, $netPrice] = $item;
            $unitCost = $currency === 'PHP' ? $netPrice : round($netPrice * (self::DEMO_RATES[$currency] ?? 1), 4); // GR value is in pesos
            $amount = round($qty * $unitCost, 2);
            $total += $amount;
            $quantity += $qty;

            // The material (SAP MATNR) is in the P2P item master
            $material = Item::query()->firstOrCreate(['code' => $code], ['name' => $name, 'uom' => $uom, 'unit_cost' => $unitCost, 'is_active' => true]);

            return SapGoodReceiptItem::create([
                'p2p_sap_good_receipt_id' => $receipt->id,
                'purchase_order' => $purchaseOrder->purchase_order,
                'purchase_order_item' => sprintf('%05d', ($i + 1) * 10),
                'item_numbering' => 1,
                'material_doc_year' => (int) now()->format('Y'),
                'material_doc_item' => '0001',
                'storage_location' => $sloc,
                'item_id' => $material->id,
                'item_name' => $name,
                'quantity_po' => $poQty,
                'quantity_served' => $qty,
                'uom' => $uom,
                'unit_cost' => $unitCost,
                'net_price' => $netPrice,
                'price_unit' => 1,
                'currency' => $currency,
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
            'quantity_total' => $grQuantity,
            'quantity_served' => $served ? $grQuantity : 0,
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

        return [
            'state' => $state,
            'items' => $sapItems,
            'quantity' => $quantity,
            'open' => array_sum(array_column($items, 4)) - $grQuantity,
            'total' => $total,
        ];
    }

    /**
     * Open escalations for an escalated demo invoice, so the Escalations queue and
     * the workflow stepper show real records (mirroring the status a hop would have
     * produced).
     *
     * @param  array<int, array{state: GoodReceiptState, items: Collection<int, SapGoodReceiptItem>, quantity: int, open: int, total: float}>  $receipts
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
