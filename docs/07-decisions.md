# Decision Log

Newest first. Each entry gives the decision and the reason for it.

| Date | Decision | Why |
|---|---|---|
| 2026-10-13 | **Charging first relaxed:** a cost center takes any G/L of its type (3rd digit 1–2 = OPEX → G/L 6…, 3+ = MFG → G/L 5…); the cost center → G/L mapping is **no longer enforced** (form and mass upload). Supersedes the mapping half of 2026-10-07 | Legacy's mapping is incomplete (e.g. 112122 lacks 6006000) and the legacy form never enforced it; real requests were being rejected |
| 2026-10-14 | **Chat bubble everywhere:** on every RFP and P2P page. Record pages (RFP view/edit, P2P AP and GR screens) open that record's thread; list and create pages open an **inbox** of the user's conversations; each table row has a chat icon with its unread count. **Vendors take part** in P2P threads on their own invoices; staff messages are **Internal by default** (hidden from the vendor) and can be switched to vendor-visible | User decision |
| 2026-10-14 | **Comments are shared:** one polymorphic `comments` table (`commentable_type` alias such as `rfp`) for RFP and P2P, model `App\Models\Comment`, trait `HasComments`. Replaces `rfp_comments` and the planned `p2p_invoice_comments`; other initiatives still get separate tables | User decision: one floating chat widget can serve every document type |
| 2026-10-13 | Premium initiatives ([10](10-premium-initiatives.md)) are done **RFP first**, then P2P. RFP and P2P get **separate tables** for each feature (e.g. `rfp_comments`, later `p2p_invoice_comments`) | User decision: they are separate systems |
| 2026-10-13 | **RFP items mass upload** (legacy "Upload Batch Items Listing"): an Excel template (Items sheet + Instructions and master data sheets: companies, sub categories by GL, cost centers with allowed GLs, vendor types, vendors, one-time vendors, currencies) and an upload that fills the Items repeater. Columns matched by header name; **GL Number = Sub Category**; the same charging-first / OPEX-MFG rules as the form; all rows must be valid or nothing is added | User request; replaces typing many receipts by hand |
| 2026-10-13 | Request types keep their labels: **Standard** = vendors and other parties outside Ramcar (including supplier down payments); **Reimbursement** = paying back an internal employee. "Vendor Payment" / "Employee Reimbursement" were declined | User decision: familiar to legacy users |
| 2026-10-12 | **RFP Approvals** is only for **Admin, AP Processor, AP Supervisor and AP Manager**. Treasury and business approvers (Line Manager, Department Head, …) do not get it; their approval screen comes with the approval engine | User decision |
| 2026-10-12 | **My Requests** (`/hub/rfps`) = legacy "My RFPs": only your own RFPs, even for Admins. **RFP Approvals** (`/hub/rfp-approvals`) = legacy "Approvals": the approver/Accounting queue. Named "RFP …" so **P2P Approvals** can be added beside it | User decision |
| 2026-10-12 | An RFP records who must act next: `current_approver_role`, plus a person when one is assigned (legacy assigns people; with none, anyone with the role may act). Keeps R3 open for new RFPs | Needed for the queue and the legacy data |
| 2026-10-12 | **Legacy RFP data is imported** (`rfp:import-legacy`, see [09](09-legacy-rfp-import.md)): RFP, item and one-time vendor ids kept; users matched by email with their legacy password hash and mapped roles; legacy master data imported by code and demo master data deactivated. Replaces "legacy-database import dropped" | User wants real data to work with |
| 2026-10-12 | New status **Rejected** (final); history rows can record an **action** without a status change | Legacy has 153 rejected RFPs and action-style history |
| 2026-10-07 | **Charging first:** an RFP line picks a **cost center or a PO no.** before the sub category. With a cost center, sub categories are limited to G/Ls mapped to it (`cost_center_gl_account`) **and** matching its expense type from the 3rd digit of the code (1–2 = OPEX → G/L 6…, 3+ = MFG → G/L 5…; `CostCenter::allowsGlCode()`). With a PO, only PO-type sub categories (e.g. Supplier Down Payment) | AP request: stops wrong-G/L charging between OPEX and MFG |
| 2026-10-07 | Step 5 status is **For Accounting Approval** for both AP Supervisor and AP Manager; either can **Return to AP Processor** (answers R7) | AP enhancement request (I. Dela Cruz, 2026-10-07) |
| 2026-10-07 | Cancelling needs remarks, always visible to AP; Step 4 shows a **total Net of Withholding Tax** | AP enhancement request |
| 2026-10-07 | RFP lists sort by RFP no., created date and last update, and the search bar also matches line **invoice numbers** | AP enhancement request |
| 2026-10-06 | RFP form follows the **legacy field set**, laid out compact: currency **per line** (replaces "one currency per request"), account group → payee ("Final payee" on Reimbursement) instead of the Pay-to toggle, vendor per line on Reimbursement, supporting documents **per line**, requestor can tick **Pre-approved** with proof of approval | User decision: keep the form familiar to legacy users |
| 2026-10-06 | **Due type** is a table (`rfp_due_types`), not an enum; due date = processing days from submission, skipping weekends by default; requestors cannot edit the date | Admins change the days without a deploy; fixes the legacy Saturday bug |
| 2026-10-06 | ~~RFP uses **one currency per request**~~ (superseded the same day: currency per line) | — |
| 2026-10-06 | Submitting needs a due type, at least one line, a total > 0, documents on every line, a rate for every line currency, and proof of approval when pre-approved; otherwise the draft is kept | Prevents incomplete RFPs reaching AP |
| 2026-10-06 | Line items use a **compact, Excel-like table repeater** (`sf-sheet`), including RFP items (documents per row; Reimbursement columns appear only on a Reimbursement) | User requirement: smooth, compact UI |
| 2026-10-06 | **P2P data comes from SAP**: POs/GRs are synced and read-only. A P2P submission always starts by choosing synced GRs, and corrections happen in SAP via escalation, never in the portal. | SAP is the system of record for purchasing |
| 2026-10-06 | **No SAP integration for now; use generic demo data** where SAP data is needed (`DemoMasterDataSeeder`). The legacy-database import is dropped for now. | Build and test the flows without depending on SAP |
| 2026-10-06 | Master data only before module work: no RFP or P2P transactions yet | Build module by module on a solid base |
| 2026-10-06 | Role name **AP Supervisor** (not AP Checker) | Matches the business flow wording; same person as P2P "checker" |
| 2026-10-06 | One seeder file (`RoleAndUserSeeder`) for roles + demo users, **truncates first**, password `TestPass123!`, owner `reg.ebalobor@kaisa.com` always first | Repeatable test data; blocked in production |
| 2026-10-06 | Step 5 is **always AP Supervisor → AP Manager** | Revised 2026 flow removed the 50K/foreign split |
| 2026-10-06 | "President, Enterprise Controller" tier approves **sequentially** | Confirmed by user |
| 2026-10-06 | **Direct to VP** applies to both Standard and Reimbursement | Confirmed by user |
| 2026-10-06 | Company 5000 short name **EERI** (legacy used "EG") | Matches business usage |
| 2026-10-06 | A threshold amount belongs to the **lower tier**; the matrix uses the PHP amount | Matches "Above 100K" wording; avoids FX ambiguity |
| 2026-10-06 | Approvers are resolved from **Spatie roles**; "BU" is a role too | Assignment stays dynamic and is managed in the UI |
| 2026-10-06 | Step 3 matrix is **data** (`rfp_approval_rules` / `_steps`), editable by Admins | Thresholds change without a deploy |
| 2026-10-06 | **Shared vendor master** (`vendors` + `company_vendor`) | SAP vendor master is the same for both modules; links supplier down payment (RFP) to P2P invoices |
| 2026-10-06 | Master data is **deactivated, not deleted** | Transactions reference it |
| 2026-10-06 | Changes to the database are made with **new migrations only** | Safe for any environment; no rollbacks of run migrations |
| 2026-10-05 | **Separate approval resources** for RFP and P2P, each with a count badge (not tabs on one page) | User preference |
| 2026-10-05 | Start with **RFP**, then P2P | User decision |
| 2026-10-05 | Legacy apps are **read-only references**; rebuild on Laravel 13 / Filament 5 / Livewire 4 | Reference APIs differ; legacy has known security and correctness issues |
| 2026-10-05 | P2P UX benchmark: **Medius** (invoice side by side with PO/GR) | User requirement |
