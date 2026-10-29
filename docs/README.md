# Ramcar AP — Documentation

Ramcar AP puts two legacy finance systems into one Laravel + Filament app:

- **RFP (Request for Payment):** internal employees, and suppliers for down payments, request a payment. It goes through business approvers and then Accounting.
- **P2P (Procure-to-Pay):** external vendors submit invoices against SAP POs and GRs. Accounting checks them and parks them in SAP.

The two modules share users, roles, companies, vendors and SAP master data. Their transaction tables stay separate.

## Read in this order

| # | Document | What it answers |
|---|---|---|
| 1 | [Roadmap & system flow](01-roadmap.md) | What we are building, in which order, and what is done |
| 2 | [Roles & users](02-roles-and-users.md) | Who uses the system, what each role can do, demo logins |
| 3 | [RFP approval flow](03-rfp-approval-flow.md) | The RFP process step by step, including the 2026 approval matrix |
| 4 | [P2P flow](04-p2p-flow.md) | The P2P process (vendor invoice → AP → SAP) as we will rebuild it |
| 5 | [Data model](05-data-model.md) | Shared and module tables, and what each one stores |
| 6 | [Development guide](06-development-guide.md) | Setup, conventions, seeding, testing, definition of done |
| 7 | [Decision log](07-decisions.md) | Decisions already made, and why |
| 8 | [Open questions](08-open-questions.md) | Questions still waiting for an answer |
| 9 | [Legacy RFP import](09-legacy-rfp-import.md) | How `rfp:import-legacy` brings over real RFP Portal data, and how legacy codes map to our tables |
| 10 | [Premium initiatives](10-premium-initiatives.md) | The feature plan for making RFP and P2P a premium app, and its status |
| 11 | [Benchmarks](11-benchmarks.md) | Best-in-class tools (Stampli, Coupa, Medius, Concur, …), what we borrow from each, and candidate ideas |
| 13 | [Hidden pages](13-hidden-pages.md) | Old pages hidden from the menu after their redesign, the ones to hide next, and how to hide one |

Also: [RFP presentation](rfp-presentation.md), slide-by-slide notes on the new RFP's highlights (kept current as features ship).

Also: [Feature & enhancement catalogue](new-feats.md), the net-new P2P + RFP features, excluding parity already in the base systems.

Also see [TECH_STACK.md](../TECH_STACK.md) for the installed packages and versions.

## Ground rules

1. `../p2p` and `../rfp-portal` are **read-only references**. Never edit them.
2. The **approval process is strict.** Status flows must match the approved flow documents exactly. Table design may improve on the legacy schemas.
3. We build **module by module**: RFP first, then P2P.
4. Every change ships with Pest tests. The suite must stay green.
