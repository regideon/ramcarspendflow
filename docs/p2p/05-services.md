# 5. Services, Jobs & Console

_Last updated: 2026-10-10 · Sources: `../p2p/app/Services`, `app/Jobs`, `app/Console`, `app/Escalation`_

Domain logic lives in services (not Filament pages or models). Slow work is **queued**.
Legacy class names are kept where they still describe the behaviour, under a `P2p`
namespace.

## 5.1 Domain services

| Service | Ports | Purpose |
|---|---|---|
| `P2pPurchaseOrderStatusService` | `PurchaseOrderStatusService` | **Single writer** of PO status (derived from GR states) |
| `P2pGoodReceiptSubmissionService` | `GoodReceiptSubmissionService` | Create/refresh submission, submit to AP, bill quantities (partial), recalc PO |
| `P2pGoodReceiptStatusService` | *new* | Compute per-GR state from invoice lines + escalations (normalization); replaces the hand-synced GR status |
| `P2pGoodReceiptReturnService` | `GoodReceiptReturnService` | Return to Vendor / Return to AP Processor |
| `P2pGoodReceiptReversalService` | `GoodReceiptReversalService` | Apply a SAP reversal; link replacement GR |
| `P2pReversalDetectionService` | `ReversalDetectionService` | Detect `BWART 102` reversals; auto-resolve open escalations |
| `P2pTaxSummaryService` | `TaxSummaryService` | VAT/EWT summary (vatable_sales, vat, total, ewt, net) |
| `P2pThreeWayMatchService` | `ThreeWayMatchService` | PO vs GR vs invoice match result |
| `P2pEscalationRoutingService` | `EscalationRoutingService` | Start/advance/resolve escalations; `startPerGoodReceipt()` |
| `P2pMaterialClassificationService` | `MaterialClassificationService` | Resolve SLOC → classification; manual override |
| `P2pCurrencyService` | `CurrencyService` | Currency lookups |
| `P2pDownPaymentService` | `DownPaymentService` | PO down-payment read/state |
| `P2pSapApiLogsService` | `SapApiLogsService` | Persist SOAP audit (redact passwords) |
| `P2pMail\EscalationMailService` | `Mail\EscalationMailService` | Hop + origin notices (**queued**) |

## 5.2 Escalation framework (`StepAction`)

Data-driven hops with optional custom actions:

| Class | Purpose |
|---|---|
| `StepAction` (interface) | Contract for a custom hop action |
| `StepActionRegistry` | Resolve `action` string → handler |
| `StepActionContext` / `StepActionCompletion` | Inputs and result of a hop |
| `ReverseAndReGrAction` | Warehouse "Reverse & re-GR" hop action |
| `StepActionServiceProvider` | Register actions |

## 5.3 Jobs

| Job | Notes |
|---|---|
| `SyncP2pPurchaseOrders` | Per-vendor PO/GR sync. Queues `user-sync` (manual) / `daily` (08:00). Dedup via atomic `Cache::add()` keyed `p2p-po-sync:pending:{vendor}`; `tries=1` + backoff; **batched** reads (no per-item MSEG/EKET fan-out); circuit breaker. Replaces legacy `SyncSapPurchaseOrders` |

> Legacy `timeout=1800` + per-item fan-out caused the documented job bloat. The rebuild
> must batch OData reads and cap fan-out (`07-sap-integration.md`, `08-conventions-and-migration.md`).

## 5.4 Console commands

| Command | Purpose |
|---|---|
| `p2p:sync-daily-purchase-orders` | 08:00 Asia/Manila, per enabled vendor, 7-day window |
| `p2p:sync-acdoca` | Poll parked/posted → completed (**deferred with SAP write side**) |
| `p2p:send-overdue-warnings` | 3+ day escalated; **guarded by `warning_sent_at`** (fixes legacy re-send) |
| `p2p:sync-purchase-orders` | Backfill (`--sync`, `--all-vendors`, `--resume`, `--unknown-vendors=skip|create`, checkpointing) |
| `p2p:backfill-statuses` | Recompute PO statuses (dry-run default, `--write`) |
| `p2p:backfill-storage-locations` | Backfill SLOC/material type |

Registered via `routes/console.php` (Laravel 11+ auto-registers `app/Console/Commands`).

## 5.5 Mailables (queued)

| Mailable | Recipients | Trigger |
|---|---|---|
| `EscalateToProcurementMail` | users of the current hop role | any escalation action |
| `EscalatedInvoiceNoticeMail` | vendor submitter / vendor email | vendor-initiated escalation |
| `ProcurementWarningMail` | active Points of Contact | overdue warning |
| `GoodReceiptCancelledMail` | vendor | cancellation |

All **queued** (legacy sent synchronously with a hardcoded BCC — not carried over).
Filament database notifications are used for interactive feedback (e.g. "SAP sync complete").

## 5.6 Support classes & traits

| Class | Purpose |
|---|---|
| `Support\ActiveCompany` | Current active company selector |
| `Support\WarehouseClassification` | SLOC → warehouse routing |
| `Support\ThreeWayMatchResult` | Match result value object |
| `Support\MaterialTypeFilter` | Material-type filtering |
| `Support\GoodReceiptActionLabel` | Action labels (Process/View/Submit to AP/Escalate) |
| `Traits\ScopesToVendor` | Vendor-scoped queries |
| `Traits\ScopesToMaterialType` | Material-type scoped queries |
| `Traits\HasGoodsReceiptRedirect` | Redirect helpers |

## 5.7 Enums

`P2pStatus`, `P2pPurchaseOrderStatus`, `P2pThreeWayMatchStatus`, `P2pDownPaymentStatus`
— see [Data model §2.7](02-data-model.md#27-enums). Material buckets are the
`p2p_material_classifications` table, not an enum. Role names use `App\Enums\RoleName`.
