# Epic 1 — Database

**Labels:** `P2P` (+ `P2P`,`RFP` for shared) · **Phase:** 1 · **Depends on:** Epic 0

Migrations, models, enums and seeders for the normalized P2P schema
([../02-data-model.md](../02-data-model.md)). New migrations only.

| ID | Title | Labels | Depends on |
|---|---|---|---|
| P2P-1.1 | SAP mirror migrations | P2P | — |
| P2P-1.2 | Working tables (normalized core) | P2P | 1.1 |
| P2P-1.3 | Escalation tables | P2P | 1.2 |
| P2P-1.4 | P2P master-data tables | P2P | — |
| P2P-1.5 | Infrastructure tables | P2P | — |
| P2P-1.6 | P2P enums | P2P | — |
| P2P-1.7 | Models, relationships, factories | P2P | 1.1–1.6 |
| P2P-1.8 | Demo master-data seeders | P2P, RFP | 1.4, 1.7 |
| P2P-1.9 | Demo transaction seeders | P2P | 1.8 |
| P2P-1.10 | Roles & demo users | P2P, RFP | 1.6 |

---

### P2P-1.1 — SAP mirror migrations
- **Scope:** `p2p_sap_purchase_orders`, `p2p_sap_good_receipts`, `p2p_sap_good_receipt_items` (see data model §2.3). Include down-payment columns on POs.
- **Acceptance:** migration runs on MySQL and in-memory SQLite; columns match the SAP field mapping.

### P2P-1.2 — Working tables (normalized core)
- **Scope:** `p2p_invoices` (authoritative status), `p2p_invoice_lines` (bill-by-quantity), `p2p_good_receipt_states` (derived), `p2p_approval_histories`.
- **Acceptance:** FKs to mirror + shared tables; decimal(15,2) money; soft deletes where needed.

### P2P-1.3 — Escalation tables
- **Scope:** `p2p_escalations`, `p2p_escalation_reasons`, `p2p_escalation_reason_steps` with `batch_number`, `from_status`, `action`, `override_header_status`.
- **Acceptance:** data-driven routes seedable; per-GR escalation + batch grouping supported.

### P2P-1.4 — P2P master-data tables
- **Labels:** `P2P`
- **Scope:** `p2p_items`, `p2p_delivery_types`, `p2p_material_classifications`, `p2p_sloc_classifications`, `p2p_material_classification_user`, `p2p_email_notification_receivers`.
- **Acceptance:** SLOC unique per `{company_code, plant, storage_location}`; `is_active` deactivation.

### P2P-1.5 — Infrastructure tables
- **Scope:** `p2p_sap_api_logs`; reuse shared Filament `imports`/`exports`/`failed_import_rows`.
- **Acceptance:** audit columns store redacted request/response + exec time.

### P2P-1.6 — P2P enums
- **Scope:** `P2pStatus` (replicate IDs 1–21), `P2pPurchaseOrderStatus`, `P2pThreeWayMatchStatus`, `P2pDownPaymentStatus`, `P2pMaterialType`.
- **Acceptance:** every status referenced by key, none by magic number; PHPUnit covers key↔value.

### P2P-1.7 — Models, relationships, factories
- **Scope:** Eloquent models under `App\Models\P2p\*`; `#[Fillable]`/`#[Table]`, `casts()`, typed relationships; factories.
- **Acceptance:** no N+1 in list queries; factories usable in tests.

### P2P-1.8 — Demo master-data seeders
- **Scope:** `P2pDemoMasterDataSeeder` (items, delivery types, classifications/SLOC, escalation reasons, POCs), re-runnable, production-blocked; extend `RoleAndUserSeeder` with P2P logins.
- **Acceptance:** `php artisan db:seed --class=P2pDemoMasterDataSeeder` is idempotent.

### P2P-1.9 — Demo transaction seeders
- **Scope:** `P2pDemoTransactionSeeder` covering every demo scenario ([../10-demo-data-plan.md](../10-demo-data-plan.md)).
- **Acceptance:** vendor/PO/GR/item data is internally consistent; statuses land as specified.

### P2P-1.10 — Roles & demo users
- **Labels:** `P2P`, `RFP`
- **Scope:** confirm `RoleName` coverage (Vendor, AP Processor, Procurement, Warehouse*); record AP Checker blocker (B1).
- **Acceptance:** seeder creates demo logins; roles referenced only via enum.
