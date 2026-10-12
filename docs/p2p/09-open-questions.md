# 9. Open Questions

_Last updated: 2026-10-10 · When answered, move to `docs/07-decisions.md`._

## Blockers

| # | Question | Why it matters | Owner |
|---|---|---|---|
| **B2** | For P2P, does the AP Supervisor/Checker "Return to AP Processor" or also "Return to Vendor"? | Checker action set | Business |

> **B1 resolved (2026-10-12):** the legacy P2P "AP Checker" is `RoleName::ApSupervisor`, not a new role. See [07-decisions](../07-decisions.md).

## Confirmations

| # | Question | Why it matters |
|---|---|---|
| C1 | Partial-GR numbers: does SAP require the physical `-P` number (in `gr_numbers` / ACDOCA), or can we bill by quantity only? | Normalization vs SAP compatibility (`02-data-model.md §2.6`) |
| C2 | Confirmed SAP write side = **AL11 file interface** (plan 261001)? | Phase 5 go-live |
| C3 | Which legacy statuses are still needed vs reserved (`3/5/7/11`)? | `P2pStatus` enum surface |
| C4 | Down payment (RFP) ↔ P2P invoice linking: is the DP % fixed or entered, and how is the balance linked? | RFP 2.2 / P2P link |
| C5 | Tuesday mancom demo (2026-10-13): must it show working PO/GR screens, or seeded data + design docs? | Epic ordering (`10-demo-data-plan.md`) |
| C6 | Vendor sees Pricing columns on P2P forms? (legacy hid them) | UI |

## Deferred (with SAP integration)

| # | Question | Why it matters |
|---|---|---|
| D1 | Which SAP OData endpoints exist for vendors (LFA1/LFB1), G/L (SKA1/SKB1), cost centers (CSKS), FX (TCURR)? | Master-data sync scope |
| D2 | ACDOCA poll cadence + batch size for the rebuild | Job design |
