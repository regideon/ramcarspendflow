# 1. Scope & Parity

_Last updated: 2026-10-10 · Sources: `../p2p` (code), P2P master doc `261002`, `docs/04-p2p-flow.md`_

This maps every legacy P2P feature to a decision: **Port** (same behaviour),
**Redesign** (same business rules, new shape), or **Drop** (not carried over).

## 1.1 Feature inventory

| # | Legacy feature | Decision | Notes |
|---|---|---|---|
| 1 | SAP PO/GR/item sync (OData) | **Redesign** | Queued, batched, retried, circuit-breaker; fixes job bloat |
| 2 | Vendor "Sync from SAP" button | Port | Per-vendor pending lock + DB notification |
| 3 | Vendor selects pending GR(s) → creates submission | Port | Bulk selection; submission = `p2p_invoices` |
| 4 | Submit to AP / Escalate | Port | |
| 5 | Partial submission (`-P1`, `-P2` clones) | **Redesign** | Bill by quantity; no local row cloning (see §1.3) |
| 6 | AP Processor review (tax/VAT/WHT) | Port | `TaxSummaryService` |
| 7 | AP Supervisor/Checker approve → SAP park | **Deferred** | Blocker: AP Checker role identity (decision #5) |
| 8 | Checker reject | Deferred | With #7 |
| 9 | Escalations (data-driven routes) | Port | `escalation_reasons` + steps; +Reverse & re-GR action |
| 10 | Return to Vendor / Return to AP Processor | Port | |
| 11 | Cancel request (vendor, on a returned request) | Port | |
| 12 | ACDOCA status poll (parked → posted → completed) | Deferred | With SAP write side (#7) |
| 13 | Three-way match badge (PO vs GR vs invoice) | Port | `ThreeWayMatchService` |
| 14 | GR reversal detection (`BWART 102`) | Port | `ReversalDetectionService`; auto-resolve escalations |
| 15 | Material/SLOC classification | Port | Data-driven; AP Finance classifies unknowns |
| 16 | Points of Contact (warning emails) | Port | `email_notification_receivers` |
| 17 | Overdue warnings | Port (fixed) | Guarded by "warning sent at" (legacy re-sent daily) |
| 18 | CSV/Excel export of AP headers | Port | Filament exporter |
| 19 | SAP down-payment tracking on PO | Port | Read-only mirror columns |
| 20 | Public/unauthenticated GR page | **Drop** | Security |
| 21 | `/db/fresh`, `/insert-*` debug routes | **Drop** | Security (statuses become enums) |
| 22 | `TransactionResource` (disabled) | **Drop** | Dead code |
| 23 | `point_of_contacts` (unused) | **Drop** | Replaced by `email_notification_receivers` |
| 24 | Synchronous mail + SOAP | **Redesign** | Queued jobs and queued mail |
| 25 | TLS verification off; XML by string build | **Redesign** | Proper HTTP client, escaped payloads |
| 26 | Hard delete of unsubmitted GR items | **Redesign** | Keep audit trail (soft delete) |
| 27 | Numeric status magic values + lookup table | **Redesign (documented)** | PHP backed enum; IDs replicated for parity |

## 1.2 What "done" means

P2P is done for this pass when, on **fresh seed data**:

- A Vendor can view synced POs/GRs, select pending GR(s), and submit to AP (partials included).
- An **AP Processor** can review, set tax/WHT, see the VAT/EWT summary, submit, return to vendor, or escalate.
- **Escalations** route through Procurement/Warehouse hops and resolve back to the originator.
- The **side-by-side viewer** shows the invoice PDF next to PO/GR lines with a 3-way-match badge.
- **P2P Settings** manage the module's master data.
- Every status transition has a Pest test.

Checker/park, ACDOCA, and the SAP write side are explicitly **out of this pass**.

## 1.3 Partial submission — legacy vs redesign

**Legacy:** partial billing clones the GR row into `{GR}-P1`, `{GR}-P2`…; PO↔GR
lookups match `LIKE '{base}-P%'`; unsubmitted items are hard-deleted.

**Redesign:** bill by **quantity**. `p2p_invoice_lines.quantity_billed` reduces the
mirror item's remaining quantity; a GR stays billable while remaining > 0. GR state
is **derived** by `GoodReceiptStatusService`, not a second hand-synced status column.
Preserve SAP's physical partial number only if SAP requires it (see §2.6 mapping).

Full detail in [Data model §2.5](02-data-model.md#25-the-normalization) and
[Workflow & statuses §4](06-workflow-statuses.md#4-partial-submission-normalized).

## 1.4 Scope line for this pass

- **In:** Vendor → AP Processor, PO/GR views, escalations, P2P Settings, export, 3-way match.
- **Out (blocked/deferred):** AP Supervisor/Checker approval + SAP park, ACDOCA posted/cleared,
  AP Manager, real SAP go-live, legacy data import.
