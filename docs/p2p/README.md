# Ramcar AP — P2P Module Documentation

_Last updated: 2026-10-10 · Branch: `p2p/docs/initial-documentation`_

Ramcar AP unifies two legacy systems into one Laravel 13 / Filament 5 /
Livewire 4 app (single panel `hub`):

- **RFP** — internal payment requests (already built).
- **P2P** — vendor invoices matched against SAP POs/GRs (**this effort**).

The legacy P2P system lives read-only at `../p2p` (Laravel 12 / Filament 4 /
Livewire 3 / PHPUnit) and is the **behavioural specification** — not code to copy.
P2P is delivered **module by module**, in phases:

| Phase | Deliverable | Status |
|---|---|---|
| 0 | Documentation (this folder) + demo data for the mancom demo | 🚧 current |
| 1 | Database: migrations, models, enums, seeders (incl. demo master data) | ⬜ |
| 2 | Services (domain + SAP, jobs, console) | ⬜ |
| 3 | Filament resources, custom pages, side-by-side viewer | ⬜ |
| 4 | Other Laravel components (policies, traits, support, routes) | ⬜ |
| 5 | Other (security hardening, Knowledge Base, exports, SAP go-live) | ⬜ |

## Decisions locked (2026-10-10)

| # | Decision | Consequence |
|---|---|---|
| 1 | Docs first; **plus generic demo data for Tuesday's mancom (2026-10-13)** | Demo seeders are an early Phase-1 deliverable |
| 2 | **Fresh data** — no legacy import | Seeders generate demo POs/GRs; no prod-clone migration |
| 3 | **Replicate legacy status IDs**; document the redesign | Flow parity now; clean redesign filed as a future target (`06-workflow-statuses.md §5`) |
| 4 | **Normalize the table split** | One workflow owner (`p2p_invoices`); GR status derived (`02-data-model.md`) |
| 5 | **AP Checker is a blocker** — build only up to **AP Processor** | Checker approve/SAP-park deferred; see `09-open-questions.md` |
| 6 | Rebuild Bulk pages + **Medius-style side-by-side viewer** | Filament 5 custom pages + Livewire 4 |
| 7 | gh-issues created with labels | `P2P` / `RFP` / `P2P, RFP` (shared) / none (new) |

## Read in this order

| # | Document | Answers |
|---|---|---|
| 1 | [Scope & parity](01-scope-and-parity.md) | What is ported, redesigned, or dropped |
| 2 | [Data model](02-data-model.md) | Tables, the normalization, enums vs lookups |
| 3 | [Master data](03-master-data.md) | Vendors, companies, SLOC/material classification, items, reasons, POCs |
| 4 | [Filament UI](04-filament-ui.md) | Nav map, resources, pages, access matrix |
| 5 | [Services](05-services.md) | Domain/SAP services, jobs, console, escalation framework, mail |
| 6 | [Workflow & statuses](06-workflow-statuses.md) | State machines, partials, returns, escalation routing, redesign |
| 7 | [SAP integration](07-sap-integration.md) | OData/SOAP/AL11, security fixes, deferred scope |
| 8 | [Conventions & migration](08-conventions-and-migration.md) | How legacy is reworked into Ramcar AP conventions |
| 9 | [Open questions](09-open-questions.md) | Blockers and confirmations |
| 10 | [Demo data plan](10-demo-data-plan.md) | Tuesday's mancom dataset (fresh, generic) |
| — | [gh-issues/](gh-issues/README.md) | Work items (GH epics + sub-issues) |

## Ground rules

1. `../p2p` and `../rfp-portal` are **read-only**. Use them as a behaviour spec only — their Filament 3/4 and Livewire 3 APIs do not apply here.
2. **SAP is the source of truth.** POs/GRs are read-only in the portal; every submission starts by choosing synced GRs.
3. **Statuses and flow are strict** — replicate the legacy IDs for flow parity (`06-workflow-statuses.md`).
4. **Adopt Ramcar AP conventions:** `p2p_*` table prefix, PHP backed enums (no lookup tables), new migrations only, `decimal` money, `DB::transaction` + row locks, everything slow on the queue.
5. Every change ships with Pest tests; the suite stays green.
