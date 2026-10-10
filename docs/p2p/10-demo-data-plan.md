# 10. Demo Data Plan — Mancom (2026-10-13)

_Last updated: 2026-10-10_

Goal: **fresh, generic demo data** (decision #2) so Tuesday's mancom walkthrough shows
P2P data end to end — no SAP dependency. Follows the RFP `DemoMasterDataSeeder`
pattern: generic, **re-runnable**, and **blocked in production**.

## 1. Seeders

| Seeder | Seeds | Notes |
|---|---|---|
| `P2pDemoMasterDataSeeder` | Items, delivery types, material classifications + SLOC mapping, escalation reasons/steps, Points of Contact | Re-runnable (`updateOrCreate`) |
| `P2pDemoTransactionSeeder` | Generic POs + GRs/items across every demo status | Re-runnable |
| `RoleAndUserSeeder` (extend) | P2P demo logins (Vendor, AP Processor; Procurement/WHS already exist) | **Truncates** users/roles — dev only |

Reuse existing shared master data: `DemoMasterDataSeeder` (companies, vendors) and
`RoleAndUserSeeder` (roles, users).

## 2. Transaction scenarios (cover the flow)

| Scenario | Status target | Demo point |
|---|---|---|
| Fresh GR, not touched | `1 Pending` | Vendor worklist |
| Submitted to AP | `2 SubmittedToAp` | Processor queue |
| AP processed, awaiting checker | `4 SubmittedToChecker` | Flow (view-only; checker blocked) |
| Returned to vendor | `15 ReturnedToVendor` | Return path |
| Escalated — quantity | `18 EscalatedToWarehouse` | WHS hop |
| Escalated — price | `10 EscalateToProcurement` | Procurement hop + reverse & re-GR |
| Partial submission | mixed | Partial/normalized billing |
| Reversed GR | `20 Reversed` | Reversal + replacement GR |

Each scenario needs a vendor, company, PO, GR and GR items consistent with the
mirror → derived model (`02-data-model.md`).

## 3. Demo logins

Use `RoleAndUserSeeder` accounts (password `TestPass123!`): `vendor@ramcar.com`,
`ap.processor@ramcar.com`, `procurement@ramcar.com`, `warehouse@ramcar.com`.

## 4. Ordering note (open — C5)

Demo data is only useful where it can be seen. If the demo must show screens, pull the
**Purchase Orders + Goods Receipt list** resources (and the Invoices/AP list) forward
from Epic 3 before the demo. If the demo is data + design docs only, seeders alone
suffice. To confirm with the user (`09-open-questions.md#c5`).

## 5. Commands

```bash
php artisan db:seed                                   # full demo dataset
php artisan db:seed --class=P2pDemoMasterDataSeeder    # re-runnable master data
php artisan db:seed --class=P2pDemoTransactionSeeder   # re-runnable POs/GRs
```
