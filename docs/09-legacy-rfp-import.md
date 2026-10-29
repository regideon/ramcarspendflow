# Legacy RFP import

_Last updated: 2026-10-23_

`php artisan rfp:import-legacy` copies the legacy **RFP Portal** database into Ramcar AP so we work with real data. It reads the `legacy_rfp` connection (`LEGACY_RFP_DB_*` in `.env`; defaults to the `rfp-portal` database on the same MySQL server).

```sh
php artisan migrate
php artisan db:seed                 # roles, demo users, master data, due types
php artisan rfp:import-legacy       # asks first; add --force to skip the question
```

- **Replaces every RFP** in the target database (items and history cascade). Safe to re-run: master data and users are matched, so nothing duplicates.
- Runs in one transaction: it imports everything or nothing.
- **Run it again after `RoleAndUserSeeder`**: that seeder truncates users and their RFPs.
- Attachment **paths** are copied; the files themselves stay on the legacy server.

## Running it

- **RFP Settings → Legacy Import** (Admin only) shows the legacy connection and the recent runs. **Run import** needs **IMPORT** typed to confirm. It runs after the page responds, so no queue worker is needed, and notifies whoever started it when done. Or run `php artisan rfp:import-legacy`.
- ⚠️ It **replaces every RFP here**, including ones created or approved in the new system.
- **Lock after go-live:** set `RFP_LEGACY_IMPORT_ENABLED=false` (the default when `APP_ENV=production`). The page disappears and the command refuses to run.
- **Approval routes:** RFPs still in approval (For AP Review … For Treasury) get their route from the current workflow, picked up where they are:
  - earlier steps are marked done ("Done in the legacy RFP Portal before the import"; the imported history says by whom);
  - the current step is active, so its approvers can act here;
  - later steps are pending.
  - RFPs that cannot be routed (e.g. no matrix tier matches) are listed as notes and stay visible without actions.

## What is imported

| Legacy | Ramcar AP | How it is matched / mapped |
|---|---|---|
| `companies`, `currencies`, `payment_methods`, `account_groups` | same tables | By code (`company_code`, `short_code`, `country`+`type`, `account_group`); missing ones are added |
| `cost_centers` (one row per cost center **and** G/L) | `cost_centers` + `cost_center_gl_account` + `gl_accounts` | By company + cost center code; G/L by code. Demo cost centers are deactivated |
| `categories`, `subcategories` | `rfp_categories`, `rfp_subcategories` | By name; the " - 6101130" suffix is dropped from sub category names. `classification_type` ["Cost Center"] / ["PO Number"] → `requires_cost_center` / `requires_po_number`. Demo categories are deactivated |
| `vendors` (one row per supplier **and** company) | `vendors` + `company_vendor` | By supplier code (`code`); per-company recon. account, payment terms, payment methods. Demo vendors without logins are deactivated |
| `one_time_vendors` | `rfp_one_time_vendors` | **Same ids**; `tax_number_1` → `tin` |
| `users` + roles | `users`, `company_user`, roles | By email. New users keep their **legacy password hash** (same login). `is_all_company` → every company |
| `rfps` | `rfps` | **Same ids** → `RFP-{year}-{id}`. Current approver: For Approval → `sequence` 1–4 = Line Manager / Department Head / Division Head / Controller (person from `current_approver_id` or `manager_id` … `controller_id`); For Accounting Processing → `accounting_level` 0–1 AP Processor, 2 AP Supervisor, 3 AP Manager, 4 Treasury |
| `rfp_items` | `rfp_items` | **Same ids** |
| `rfp_histories` | `rfp_histories` (`action` + `to_status`) | See below |

Not imported yet: AP tax entries on items (input tax, EWT, VAT; roadmap 2.7), the legacy approver chain (`rfp_approvals`, `manager_id` … `controller_id`; the 2026 matrix replaces it), SAP posting fields, cash advances.

## Code mappings (legacy enum / number → our table or enum)

**RFP status** (`status_no` + `accounting_level`) → `RfpStatus`

| Legacy | Ours |
|---|---|
| 0 Draft | Draft |
| 1 Pending | For AP Review (the Accounting Personnel's first review, legacy "Endorse / Revise"; corrected 2026-10-23) |
| 2 For Approval | For Approval (legacy used the old Manager → Dept Head → VP → Controller chain) |
| 11 For Accounting Processing, level 0–1 (Accounting Personnel) | For AP Processing |
| 11, level 2–3 (Accounting Supervisor / Manager) | For Accounting Approval |
| 11, level 4 | For Treasury |
| 12 Rejected | **Rejected** (new status) |
| 13 Returned | For Revision |
| 14 Cancelled | Cancelled |
| 15 Completed, 21 SAP Posted | Completed |
| 22 Reprocess | For AP Processing |

**Other codes**

| Legacy | Ours |
|---|---|
| `due_type` "Urgent" / "Standard" | `rfp_due_type_id` → `rfp_due_types` (`urgent`, `standard`) |
| `is_reimbursement` 0 / 1 | `request_type` Standard / Reimbursement |
| `receiver_id` (vendor row) | `vendor_id` (payee) |
| item `vendor_type` 1 / 2 | `RfpVendorType` Vendor / One-time vendor (kept on Reimbursement lines only) |
| item `cost_center_id` (a **code**, e.g. 103100) | `cost_center_id` of that code in the RFP's company |
| item amount: PHP lines keep it in `total_php_amount` | `amount` (in the line currency), `exchange_rate`, `php_amount` |
| item / header `supporting_documents` (JSON) | item `attachments`; pre-2025-11 header documents go to the first item |

**History** (`status_id` + `sequence`) → `action` (+ `to_status` when it changes the status)

| Legacy | action | to_status |
|---|---|---|
| 1, sequence 0 / 5 / other | Endorsed / Processed / Approved | — |
| 0 (rejected by email), 2 | Rejected | Rejected |
| 3 | Returned | For Revision |
| 5 | Tax codes processed | — |
| 14 | Cancelled | Cancelled |
| 15 | Completed | Completed |
| 21 | Updated | — |

## Data clean-up done on import

The command prints these as notes. From the 2026-10-12 local copy (12,118 RFPs, 34,253 items):

| Case | Rows | Handling |
|---|---|---|
| Item "cost center" is a 10-digit PO number (41…) | 1,733 | Moved to PO no. |
| Item cost center code not in the legacy master | 173 codes | Added as **inactive** cost centers ("Legacy cost center …") |
| Item sub category deleted in legacy | 9 items | Inactive stand-in sub category with G/L 0000000 |
| Items / history of a missing RFP | 2 / 1 | Skipped |
| Legacy roles "Vendor Admin", "Financial Accounting" | 3 users | No equivalent; not assigned |

Checked after import: RFP and item counts and the sum of PHP amounts match legacy exactly.
