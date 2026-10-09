# Roles & Users

_Last updated: 2026-10-06_

## 1. User types

| Type | Who | Module(s) | Notes |
|---|---|---|---|
| Internal employee | Ramcar group staff | RFP | Requests payments and reimbursements |
| Business approver | Managers up to ExCom | RFP | Approves based on the Step 3 matrix |
| Accounting | AP team | RFP + P2P | The same people work both modules |
| Treasury | Treasury team | RFP | Final step of RFP |
| Operations | Procurement, Warehouse | P2P | Resolve P2P escalations |
| External vendor | Supplier staff | P2P (+ RFP down payment) | User is linked to one SAP vendor (`users.vendor_id`) |
| Admin | System administrators | All | Master data, users, roles |

Each user has:
- a **home company** (`users.company_id`)
- the **companies they may request or approve for** (`company_user`)
- an **active** flag (inactive users cannot sign in)
- one or more **roles** (Spatie)

## 2. Roles

Role names are defined in code in `App\Enums\RoleName`. The system roles cannot be renamed or deleted in the UI, but Admins may add extra roles.

| Role | Module | Responsibility |
|---|---|---|
| **Admin** | All | Master data, RFP settings, users, roles |
| **Requestor** | RFP | Creates and submits RFPs |
| **Vendor** | P2P, RFP | Submits invoices against PO/GR; may request a down payment through RFP |
| **AP Processor** | RFP, P2P | RFP Steps 2 and 4; P2P invoice review, tax/WHT codes |
| **AP Supervisor** | RFP, P2P | RFP Step 5 (first); P2P checker/approver before SAP |
| **AP Manager** | RFP | RFP Step 5 (after AP Supervisor) |
| **Treasury** | RFP | RFP Step 6 (final) |
| **Line Manager** | RFP | Matrix approver (Reimbursement) |
| **Department Head** | RFP | Matrix approver (Reimbursement, Standard) |
| **Division Head** | RFP | Matrix approver (Reimbursement > 100K; Direct to VP) |
| **Controller** | RFP | Matrix approver (Reimbursement > 100K) |
| **BU Head** | RFP | Matrix approver (Standard 1M–5M) |
| **BU FP&A** | RFP | Matrix approver (Standard ≤ 1M) |
| **FP&A Head** | RFP | Matrix approver (Standard 1M–5M) |
| **Enterprise Controller** | RFP | Matrix approver (Standard 5M–25M; Direct to VP > 100K) |
| **President** | RFP | Matrix approver (Standard 5M–25M) |
| **ExCom / Chairman** | RFP | Matrix approver (Standard > 25M) |
| **Procurement** | P2P | Escalation hop (wrong description/UoM, price difference) |
| **Warehouse** | P2P | Escalation hop (quantity, incomplete GR, reverse & re-GR) |
| **Warehouse - Raw Materials** | P2P | Warehouse hop for raw-material lines |
| **Warehouse - Spare Parts** | P2P | Warehouse hop for spare-part lines |

## 3. Legacy role mapping

| Legacy P2P | Legacy RFP | Spend Flow |
|---|---|---|
| `admin` | Admin | Admin |
| `supplier` | Vendor Admin | Vendor |
| `processor` | Accounting Personnel | AP Processor |
| `checker` | Accounting Supervisor | AP Supervisor |
| — | Accounting Manager | AP Manager |
| — | Treasury | Treasury |
| — | Requestor | Requestor |
| — | Immediate Supervisor / `is_manager` flag | Line Manager |
| — | Department Head / `is_department` flag | Department Head |
| — | Division Head / `is_vp` flag | Division Head |
| `procurement` | — | Procurement |
| `warehouse*` | — | Warehouse roles |
| — | Approver, GM, Controller 1/2, Disbursement Team, Financial Accounting | **Dropped** |

## 4. Demo logins (local / UAT only)

Created by `php artisan db:seed --class=RoleAndUserSeeder`. The seeder **truncates users and roles first** and refuses to run in production.

- Password for every account: **`TestPass123!`**
- Every account has access to all 4 companies; home company is PBI.

| Email | Role |
|---|---|
| reg.ebalobor@kaisa.com (always id 1) | Admin |
| admin@ramcar.com | Admin |
| requestor@ramcar.com | Requestor |
| vendor@ramcar.com | Vendor (linked to demo vendor `DEMO000001`) |
| ap.processor@ramcar.com | AP Processor |
| ap.supervisor@ramcar.com | AP Supervisor |
| ap.manager@ramcar.com | AP Manager |
| treasury@ramcar.com | Treasury |
| line.manager@ramcar.com | Line Manager |
| department.head@ramcar.com | Department Head |
| division.head@ramcar.com | Division Head |
| controller@ramcar.com | Controller |
| bu.head@ramcar.com | BU Head |
| bu.fpa@ramcar.com | BU FP&A |
| fpa.head@ramcar.com | FP&A Head |
| enterprise.controller@ramcar.com | Enterprise Controller |
| president@ramcar.com | President |
| excom.chairman@ramcar.com | ExCom / Chairman |
| procurement@ramcar.com | Procurement |
| warehouse@ramcar.com | Warehouse |
| warehouse.raw.materials@ramcar.com | Warehouse - Raw Materials |
| warehouse.spare.parts@ramcar.com | Warehouse - Spare Parts |

## 5. Screen access today

| Area | Who |
|---|---|
| Settings menu (Master Data, RFP Settings, Users, Roles) | Admin only |
| Panel `/hub` | Any active user |
| RFP / P2P screens | Defined per module as they are built |
