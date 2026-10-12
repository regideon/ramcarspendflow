# 3. Master Data & Setup

_Last updated: 2026-10-10 · Sources: `../p2p` seeders + migrations, `docs/05-data-model.md`_

P2P reuses the shared master data the RFP module already ships, and adds P2P-only
setup under **Settings → P2P Settings**. All setup data is **deactivated, not deleted**;
SAP codes are the natural unique key, scoped per company/plant where SAP is.

## 3.1 Shared master data (already exists — align, don't recreate)

| Concern | Shared table | P2P usage |
|---|---|---|
| Company codes (BUKRS) | `companies` | PO/GR `company_code`; per-company SLOC scoping |
| Vendors (LIFNR) | `vendors` + `company_vendor` | Vendor login link, PO/GR vendor, WHT chain |
| Currencies / FX | `currencies`, `exchange_rates` | PO/GR/invoice currency |
| Input VAT codes (MWSKZ) | `tax_codes` | Processor VAT selection (`category` + `rate`) |
| Withholding tax | `withholding_tax_types`, `withholding_tax_codes` | Processor WHT code; rate from SAP with fallback |
| Users & roles | `users`, Spatie roles/`company_user` | Vendor ↔ one SAP vendor; staff company access |

> `companies.short_name`, `vendors.email/tax_number/currency/last_po_synced_at`,
> `tax_codes.category`, and the `withholding_tax_codes` unique key were already widened
> for P2P in `2026_10_06_161618_update_master_data_columns_for_p2p.php`.

## 3.2 P2P-only master data (new, under Settings → P2P Settings)

| Resource | Table | Key fields | Notes |
|---|---|---|---|
| **SLOC / Material Classification** | `p2p_material_classifications` + `p2p_sloc_classifications` | classification `code`/`name`/`role_name`; SLOC `company_code`/`plant`/`storage_location`/`description` → classification | Data-driven warehouse routing; unknown SLOCs = "Unclassified", classified by AP Finance |
| Material Classification users | `p2p_material_classification_user` | user ↔ classification | Who may classify which bucket |
| **Items** | `p2p_items` | item master, `unit_cost` | Soft deletes |
| **Delivery Types** | `p2p_delivery_types` | `name` | Lookup for GR |
| **Escalation Reasons** | `p2p_escalation_reasons` + `p2p_escalation_reason_steps` | reason `code`/`name`/`origin_role`/`is_active`; step `step_order`/`role`/`status_id`/`action_label`/`action`/`override_header_status` | **Data-driven routes** (see below) |
| **Points of Contact** | `p2p_email_notification_receivers` | `email`/`name`/`notification_type`/`is_active` | Recipients of overdue-warning mail |

Vendors and Companies remain under the shared **Master Data** cluster (not duplicated
under P2P Settings).

## 3.3 Escalation routes (seeded, editable as data)

Routes come from `p2p_escalation_reasons` + `p2p_escalation_reason_steps`. Seeded routes
(confirmed with the business):

| Reason | Origin | Route (role → status) |
|---|---|---|
| Quantity Difference | vendor | warehouse `18` → vendor `15` |
| Incomplete GR | vendor | warehouse `18` → vendor `15` |
| Wrong item description | vendor | procurement `10` → vendor `15` |
| Wrong unit of measure | vendor | procurement `10` → vendor `15` |
| Price difference | processor | procurement `10` → warehouse `18` (**Reverse & re-GR**) → procurement `10` → vendor `15` |
| Other | any | procurement `10` → warehouse `18` → origin |
| Document concern | processor | **retired** — use Return to Vendor |

A hop with a custom `action` (Warehouse "Reverse & re-GR") replaces the generic Advance
button. The last hop is terminal (`override_header_status`) and hands the request back
to the originator. Status IDs reference `App\Enums\P2pStatus`.

## 3.4 Material / SLOC classification

- `p2p_material_classifications` seed: Raw Materials (`warehouse_raw_materials`),
  Spare Parts (`warehouse_spare_parts`), Unclassified (AP Finance).
- `p2p_sloc_classifications` maps `{company_code, plant, storage_location}` → classification.
  A SLOC resolves to a configured classification or falls back to **Unclassified**.
- A manual classification is authoritative: re-seeding refreshes only the `description`.
- Warehouse escalation hops route to Raw Materials or Spare Parts from the line's SLOC.

## 3.5 Ported master data (real SAP extracts)

Imported from the live P2P data (reference data, not transactions):

| Seeder | Source | Loads |
|---|---|---|
| `P2pCompanySeeder` | `p2p_prod_clone.companies` | Shared `companies` (51 SAP company codes) |
| `P2pVendorSeeder` | `database/data/P2pVendors.csv` + `LocalVendor.csv` | Shared `vendors` (7,487) + `company_vendor` extensions |
| `P2pItemSeeder` | `p2p_prod_clone.items` | `p2p_items` (2) |
| `P2pDeliveryTypeSeeder` | `p2p_prod_clone.delivery_types` | `p2p_delivery_types` (Full / Partial Delivery) |
| `P2pSlocMasterSeeder` | `database/data/T001L_SLOC.csv` | `p2p_sloc_classifications` (270), classified via `config/p2p.php` |

The generic P2P setup (material classifications, escalation reasons, points of contact)
stays in `P2pDemoMasterDataSeeder`. Seeded-from-X masters deactivate stale rows
(never delete). A manually classified SLOC is never re-classified by a re-seed.
