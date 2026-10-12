# Dashboard / Analytics Plan — P2P + RFP Unification (RamcarSpendFlow)

_Last updated: 2026-10-12_

This plan identifies the data available for a Dashboard/Analytics layer across the
unified Ramcar Spend Flow app, and sets out which datapoints each **user role** should see.
It is grounded in the live databases and the current source code.

## 1. Current state (verified against live DB + code)

| Store | Tables of interest | Volume |
|---|---|---|
| `ramcarspendflow` (new unified) | `rfps`, `rfp_items`, `rfp_histories`, `p2p_invoices`, `p2p_invoice_lines`, `p2p_escalations`, `p2p_sap_*`, `p2p_good_receipt_states` | 0 RFP · 8 invoices · 12 POs/GRs (fresh/demo) |
| `rfp_portal` (legacy RFP) | `rfps`, `rfp_items`, `rfp_approvals`, `rfp_histories` | **12,118 RFPs / 34,255 items**, 441 users, 4 companies, 278 requestors, Jul-2024 → Oct-2026 |
| `p2p_prod_clone_261007` (legacy P2P) | `good_receipt_headers`, `good_receipt_items`, `sap_purchase_orders`, `approval_histories`, `escalations` | 288 submissions, 2,713 lines, 431 POs, 7,487 vendors, 12 active vendors, 3 companies |

The new schema **already carries every field analytics needs** (timestamps, statuses, amounts,
tax breakdown, FKs). There is **no Widgets directory yet** — the panel only registers
`AccountWidget`. The two legacy datasets are the realistic backfill source, so the
datapoints below are grounded on what history actually exists.

## 2. Key design decision: two "unified" facts + a role lens

The two modules share **concepts** (a payment request flows through people) but not tables.
Analytics should be modeled as **two fact tables feeding a shared lens**:

- **RFP fact** (`rfps` + `rfp_items` + `rfp_histories`) — internal payment/reimbursement requests.
- **P2P fact** (`p2p_invoices` + `p2p_invoice_lines` + `p2p_escalations` + `p2p_sap_*`) — vendor invoices matched to SAP PO/GR.

Unifying dimension: **amount pending/approved by company, by role, by period**, with a
`source` discriminator (`rfp` | `p2p`). A third shared dimension is **aging / turnaround time**,
computed from `submitted_at` → terminal status.

## 3. Universal datapoints (all roles)

**Volume & value**
- Count + total PHP value by `status` × `source` — RFP: `total_php_amount`; P2P: `total`, `net`, `amount_submitted`.
- Records created vs completed (`completed_at`) by day/week/month (trend).
- Open vs terminal: RFP `{draft, for_ap_review, for_approval, for_ap_processing, for_revision, for_accounting_approval, for_treasury}` vs `{completed, cancelled}`; P2P non-terminal `P2pStatus` vs `{Completed, Reversed, Cancelled}`.

**Throughput / SLA**
- Average & p95 age of an open record (`now() - submitted_at`).
- Turnaround from `submitted_at`/`created_at` → `completed_at` (P2P) or `status=completed` history row (RFP).
- Aging buckets: 0–2 / 3–5 / 6–10 / 11–15 / 15+ days vs `due_date` (RFP) and `due_at` (P2P GR).
- Overdue count/value (`due_date < today` and not terminal).

**Tax & financial (P2P full, RFP partial)**
- VAT split: `vatable_sales` / `vat_exempt` / `vat_zero_rated` / `vat`.
- EWT: `ewt`, `withholding_tax_base`, `withholding_tax_code`.
- `net` payable total; gross (`total`) vs net.
- Multi-currency exposure from `rfp_items.currency_id` + `exchange_rate` (foreign totals via `Rfp::foreignTotals()`).

**Dimension breakdowns (both)**
- By `company_id` (4 companies), `vendor_id`, cost center, GL account, category/subcategory (RFP), material classification / storage location (P2P).
- By `request_type` (standard/reimbursement), `is_direct_to_vp`, `is_pre_approved`.

## 4. Role-specific datapoints

Access model is per `RoleName` (`app/Enums/RoleName.php`) with existing `visibleTo()` scopes on
`Rfp`/`Invoice`. Dashboards should key off `hasRole()` + `company_user` scope.

### 4.1 Requestor
Source: `rfps` where `requestor_id = user` (`Rfp::visibleTo`)
- My drafts / for-revision count (action needed).
- My submissions this period: count + total PHP.
- My requests by status funnel (submitted → completed).
- Turnaround of my requests (`submitted_at` → `completed`) and average time sitting with each approver (from `rfp_histories` deltas).
- Most-returned reasons (from `rfp_histories.remarks` on `for_revision`).
- Top vendors / categories I request against.

### 4.2 Vendor (supplier)
Source: `p2p_invoices` where `user_id = user` (`Invoice::visibleTo`) + RFP down-payment RFPs
- My invoices by `P2pStatus` (Pending, Returned to Vendor, Escalated by Vendor, Invoice Parked/Posted, Completed).
- Actionable: returned-to-vendor + pending GRs available to bill (from `p2p_good_receipt_states` where status Pending, vendor matches).
- Amounts submitted vs posted/cleared (`amount_submitted` vs `net`, `fi_doc_no`, `clearing_doc_no`).
- Outstanding: submitted not yet posted; aging vs `due_at`.
- My escalations raised, open/resolved (`p2p_escalations` where `origin_role='vendor'`).
- Down-payment RFPs I submitted (RFP where vendor link + `request_type`).

### 4.3 AP Processor
Source: P2P/RFP worklist for their companies
- **RFP**: my inbox = `status IN (for_ap_review, for_ap_processing)`; Step-2 first-review queue; Step-4 review queue; count/value + oldest item.
- **P2P**: submissions `status = SubmittedToAp` awaiting tax/WHT assignment; returned-to-AP count.
- Tax/WHT workload: records missing `tax_code`/`withholding_tax_code`; VAT/EWT computed vs pending.
- Escalation resolution queue (`current_role = processor`).
- My processed volume/cycle time (from `rfp_histories`/`p2p_approval_histories` where `user_id`/`actioned_by` = user).
- Returned-to-vendor / for-revision I raised (quality signal).

### 4.4 AP Supervisor
- **RFP** Step 5 inbox: `status = for_accounting_approval`.
- **P2P** checker queue: `status = SubmittedToChecker`.
- Approvals vs returns I performed; average decision time.
- Value approved to SAP (parked) this period; `fi_doc_no`/`clearing_doc_no` post status.
- Exceptions: escalated invoices aging, re-work loop counts (`ReturnedToApProcessor`).

### 4.5 AP Manager
- Step 5 (second approver) queue: `for_accounting_approval` after supervisor.
- Total valuation approved to Treasury; high-value approvals.
- Period-over-period approval throughput; return rate to AP Processor.

### 4.6 Treasury
- **RFP final** queue: `status = for_treasury`; count + total PHP, aging vs `due_date`.
- Cash-out forecast: sum `total_php_amount` due next 7/14/30 days.
- Completed payments by period; disbursement volume.
- Overdue-at-treasury list.

### 4.7 Matrix Approvers (Line Manager → President → ExCom)
Role-specific inbox derived from `rfp_approval_rules`/`rfp_approval_rule_steps` matching
`RfpStatus::ForApproval` + the current step's role (`ApprovalRule::findFor($requestType, $amountInPhp, $isDirectToVp)`)
- My pending approvals (count/value, oldest first).
- By tier: Reimbursement chains vs Standard chains; Direct-to-VP toggle.
- My approve/reject/return history + average decision time (`rfp_histories` where `user_id = user`).
- Amount bands I handle (e.g. BU Head 1M–5M; ExCom >25M).
- Backlog vs SLA per approver tier (bottleneck detection).

### 4.8 Procurement
- Escalations where `current_role = procurement` (open) — wrong description/UoM, price difference.
- Escalations by `reason` (`p2p_escalation_reasons`), by vendor, aging since `escalated_at`.
- Reverse-&-re-GR actions performed (`StepAction`), resolved count / resolution time.

### 4.9 Warehouse (+ Raw Materials / Spare Parts)
- Escalations where `current_role = warehouse` / routed by SLOC classification (raw materials vs spare parts).
- Quantity difference / incomplete GR workloads.
- Open vs resolved, time-to-resolve (`escalated_at` → `resolved_at`).
- GR reversal activity (`good_receipt_states.reversed_at`, `reversal_document`).

### 4.10 Admin (system-wide / everything)
- Global KPI board: RFP + P2P volume, value, status distribution, trend.
- Cross-module: total committed spend (RFP PHP total + P2P net), by company/period.
- Workflow health: bottleneck step (avg time per status from histories), escalation hot spots.
- Master-data health: vendors never transacted, inactive items, exchange-rate gaps.
- User/role activity: records per role, dormant approvers, SAP API log failures (`p2p_sap_api_logs`), Pulse metrics.
- Data-quality: P2P lines `classified_at IS NULL` (unclassified material), missing GRC/PO linkage.

## 5. Recommended implementation shape

- `app/Filament/Widgets/` currently does not exist — create grouped widgets: `Shared`, `Rfp`, `P2p`.
- One **base widget** resolving role → dataset; use `canView()`/`getVisibleRoles()` per widget for role gating. Filament 5 widgets support `static $sort`, `getStats()`, chart widgets (`ChartWidget`), and table widgets.
- Two query services to avoid N+1 in widgets: `RfpMetricsService` and `P2pMetricsService` (mirrors existing `App\Services\P2p\*` pattern), each exposing scope-aware aggregates. Consider one `SpendMetrics` facade returning a normalized DTO with `source`, `status`, `amount_php`, `age_days`, `company_id`, `role_step`.
- For SLA/turnaround, lean on `rfp_histories` and `p2p_approval_histories` (both have actor + from/to status + timestamp) — the highest-value dataset, and the legacy systems have years of it.
- Historical dashboards should read from a **backfill/ETL** of `rfp_portal` (12k RFPs) and `p2p_prod_clone_261007` (288 submissions) into the unified tables, since the unified DB is currently empty.

## 6. Notable gotchas found

- `rfp_portal` is real prod history; `p2p` (non-clone) and `ramcarspendflow` are effectively empty — analytics must target `ramcarspendflow` + the prod clones.
- P2P `P2pStatus` ints **intentionally mirror legacy status IDs**, so historical status columns port directly.
- RFP legacy `status_no` (0,1,2,11–15) does **not** map 1:1 to `RfpStatus` — needs a mapping table; legacy has no Draft/ForApproval granularity (approval lived in `rfp_approvals` with `sequence`).
- `p2p_invoices.user_id` is the submitter (vendor), while RFP uses `requestor_id` — role scopes differ (`Invoice::visibleTo` only filters for Vendor/Admin; AP/Procurement/Warehouse currently see everything).
- Money types differ: legacy uses narrower decimals (8,2 / 12,2); unified uses 15,2 — watch rounding on backfill.

## 7. Next steps

1. Confirm the final role → widget matrix (which widgets each role sees).
2. Build `RfpMetricsService` / `P2pMetricsService` with scope-aware aggregates.
3. Backfill legacy `rfp_portal` + `p2p_prod_clone_261007` data into the unified tables.
4. Create the Filament widgets under `app/Filament/Widgets/`.
