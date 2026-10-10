# Epic 2 — Services

**Labels:** `P2P` · **Phase:** 2 · **Depends on:** Epic 1

Domain + integration services, jobs, console, escalation framework, mail
([../05-services.md](../05-services.md)). SAP I/O sits behind interfaces so demo data
works now and real SAP can be swapped in later.

| ID | Title | Labels | Depends on |
|---|---|---|---|
| P2P-2.1 | Derived GR state service | P2P | 1.2 |
| P2P-2.2 | Submission service (partial/normalized) | P2P | 2.1, 2.3 |
| P2P-2.3 | PO status service | P2P | 1.2 |
| P2P-2.4 | Tax summary service | P2P | 1.7 |
| P2P-2.5 | Return & cancellation service | P2P | 2.2 |
| P2P-2.6 | Reversal detection & apply | P2P | 2.1 |
| P2P-2.7 | Escalation routing + StepAction framework | P2P | 1.3 |
| P2P-2.8 | Material classification service | P2P | 1.4 |
| P2P-2.9 | Three-way match service | P2P | 2.1 |
| P2P-2.10 | Down-payment & currency services | P2P | 1.1 |
| P2P-2.11 | Sync job (batched, stubbed SAP) | P2P | 2.3, 2.13 |
| P2P-2.12 | Console commands | P2P | 2.11 |
| P2P-2.13 | SAP service interfaces + stubs | P2P | 1.1 |
| P2P-2.14 | Queued mailables + notifications | P2P | 2.7 |

---

### P2P-2.1 — Derived GR state service
- **Scope:** `P2pGoodReceiptStatusService` computes Pending/Partially Billed/Billed/Returned/Escalated/Reversed from invoice lines + escalations; single writer; recompute inside `DB::transaction`.
- **Acceptance:** no hand-synced GR status column; state consistent after every action; unit-tested matrix.

### P2P-2.2 — Submission service (partial/normalized)
- **Scope:** create/refresh submission, submit to AP, bill quantities (remaining = mirror qty − Σ billed), recalc PO; row-locked.
- **Acceptance:** multiple invoices per GR; partials covered by tests; no local `-P` clones.

### P2P-2.3 — PO status service
- **Scope:** `P2pPurchaseOrderStatusService` derives PO status (0/2/3/1) from GR states.
- **Acceptance:** single writer; rejected/returned drops to Partially Submitted.

### P2P-2.4 — Tax summary service
- **Scope:** `P2pTaxSummaryService` computes vatable/exempt/zero, vat, total, ewt, net; WHT rate via SAP chain with statutory fallback (interface).
- **Acceptance:** thresholds tested on both sides; cached rate.

### P2P-2.5 — Return & cancellation service
- **Scope:** Return to Vendor, Return to AP Processor, vendor cancel on a returned request; remarks required.
- **Acceptance:** each writes history + recomputes state.

### P2P-2.6 — Reversal detection & apply
- **Scope:** detect `BWART 102` from sync, mark GR Reversed, link replacement, auto-resolve open escalations.
- **Acceptance:** reversed GR dropped from vendor form with a redirect notice.

### P2P-2.7 — Escalation routing + StepAction framework
- **Scope:** `P2pEscalationRoutingService` (+ `StepAction`, `StepActionRegistry`, `StepActionContext/Completion`, `ReverseAndReGrAction`); per-GR rows sharing a batch; derive `PartiallyEscalated`.
- **Acceptance:** seeded routes run; last hop returns to origin; custom actions replace Advance.

### P2P-2.8 — Material classification service
- **Scope:** resolve SLOC → classification; manual override; warehouse routing (Raw/Spare).
- **Acceptance:** unknown SLOC → Unclassified; manual classification survives re-seed.

### P2P-2.9 — Three-way match service
- **Scope:** PO vs GR vs invoice result value object + badge states.
- **Acceptance:** unit tests for match/mismatch cases.

### P2P-2.10 — Down-payment & currency services
- **Scope:** PO down-payment read/state; currency lookups.
- **Acceptance:** read-only mirror; no SAP write.

### P2P-2.11 — Sync job (batched, stubbed SAP)
- **Scope:** `SyncP2pPurchaseOrders` — per-vendor dedup, batched reads, retry/backoff, circuit breaker; queues `user-sync`/`daily`; SAP behind interface.
- **Acceptance:** no per-item fan-out; pending-lock dedup tested.

### P2P-2.12 — Console commands
- **Scope:** sync daily, ACDOCA (deferred), overdue warnings (guarded), backfills.
- **Acceptance:** scheduled in `routes/console.php`; warnings do not repeat.

### P2P-2.13 — SAP service interfaces + stubs
- **Scope:** `P2pSapBaseService`, read services (PO/GR/ACDOCA/tax), write service, `P2pSapApiLogsService` — behind interfaces; demo stub implementation.
- **Acceptance:** app runs end to end with demo data, no SAP.

### P2P-2.14 — Queued mailables + notifications
- **Scope:** escalation hop/origin mail, warning mail, cancellation mail — **queued**; Filament DB notifications for sync.
- **Acceptance:** `Mail::fake()`/`Queue::fake()` tests; no synchronous sends.
