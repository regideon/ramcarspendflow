# P2P + RFP Unification — Net-New Features

_Last updated: 2026-10-23_

This is the **delta list**: only features that are new or materially improved by putting RFP and P2P in one app. Parity features already in the legacy systems are excluded.

**Already in the base P2P (excluded):** SAP PO/GR sync and logs, vendor portal, GR-first multi-PO/multi-GR submission, partial billing, **invoice PDF beside PO/GR lines (the "Medius" layout)**, 3-way match, tax/WHT, SOAP park, ACDOCA status, returns/reject/resubmit, procurement escalations, material/SLOC classification, points of contact, overdue warnings, exports and approval history.

**Already in the base RFP (excluded):** request form, approval matrix and approvals queue, reminders, exports and master-data admin. The RFP approval actions are a parity rebuild still in progress, not a new feature.

Anything marked **Port** or **Redesign** in [P2P scope & parity](p2p/01-scope-and-parity.md) is treated as base.

**Status:** ✅ Built · 🟡 Partial / demo data · 🔜 Planned · 💡 Candidate

**Module / Screen:** the app area or page where the feature is found. `Process Goods Receipts` is the vendor bulk-invoice page; `Process Accounts Payable` is the AP bulk-invoice page.

## 1. Unification

| Feature | Module / Screen | Description | Status |
|---|---|---|---|
| One app, one login | Login / all screens | RFP and P2P share the `/hub` panel, identity, menu and notification bell. | ✅ |
| Shared master data | Settings → Master Data | Companies, vendors, currencies, G/L, cost centers and tax codes are maintained once. | ✅ |
| Unified roles & company access | Settings → Users / Roles | The 21 legacy roles and per-user company scope live in one model. | ✅ |
| Unified vendor experience | RFP Requests + P2P Requests | A supplier uses one account for RFP down-payment requests and P2P invoices. | 🔜 |
| Unified notification center | Notifications (bell) | One bell for handoffs, mentions and payment notices across both modules. | 🟡 |
| Cross-module duplicate check | RFP Requests + Process Goods Receipts | Warn when payee + amount + invoice/OR no. match another RFP or P2P invoice. | 🔜 |
| Down payment ↔ P2P invoice | RFP Requests + P2P Requests | A paid DP from RFP is deducted from and linked to the later P2P invoice. | 🔜 |
| Global search | Top bar (all screens) | One search for RFP, invoice, PO and vendor across both modules. | 💡 |
| Cross-module work inbox | Dashboard | A personal "Today" view across both modules, while keeping separate approval queues. | 💡 |

## 2. RFP enhancements

| Feature | Module / Screen | Description | Status |
|---|---|---|---|
| AI receipt reading | RFP Requests (New/Edit) | Reads PDFs/photos, extracts fields, suggests coding and flags duplicates; the user reviews. | ✅ |
| Charging-first guardrails | RFP Requests (New/Edit) | Cost center or PO first; only valid G/L accounts and sub-categories are offered. | ✅ |
| Multi-currency per line | RFP Requests (New/Edit) | Currency and FX per line, with the PHP total used for the matrix (base used one currency per request). | ✅ |
| Workflow Preview | RFP Requests (New/Edit) + RFP Approvals | The exact approval route is shown on the form, submit modal and record page. | ✅ |
| Requestor dashboard | Dashboard | In-flight requests, action needed, stage wait and spend trend. | 🟡 |
| Expected pay date | RFP Requests | Projects the Treasury payment date for the requestor. | 💡 |
| Policy hints | RFP Requests (New/Edit) | Inline rules such as "meals above ₱X need an attendee list", to prevent returns. | 💡 |

## 3. P2P enhancements

| Feature | Module / Screen | Description | Status |
|---|---|---|---|
| AI invoice reading | Process Goods Receipts | Reads the vendor PDF, fills quantities/reference fields and checks each line against PO/GR. | ✅ |
| Vendor dashboard | Dashboard | Action needed, ready to bill, payment outlook and turnaround. | 🟡 |
| Status stepper & timeline | P2P Requests | The vendor sees where the invoice is and the full approval history. | ✅ |
| Three-way Matching | P2P Requests | Per-line PO vs GR vs invoice match status, with flags, warnings and unmatched lines. | ✅ |

## 4. Workflow & collaboration

| Feature | Module / Screen | Description | Status |
|---|---|---|---|
| Record conversations | Comments (all record pages) | Threaded comments on RFP and P2P records, with attachments and read receipts. | ✅ |
| Chat bubble & inbox | Chat bubble (all screens) | Floating widget with a conversation inbox, unread badges and per-row chat icons. | ✅ |
| @mentions | Comments / Chat bubble | Mention permitted users; they get an in-app notification with a deep link. | ✅ |
| Handoff notifications & reminders | Notifications (bell) + RFP Approvals / P2P Approvals | In-app/email on handoff, return or payment; configurable reminders and escalation. | 🔜 |
| Reason codes | RFP Settings / P2P Settings + RFP Approvals / P2P Approvals | Standard return/cancel reasons in Settings, used in history and reporting. | 🔜 |
| Delegation / out of office | Settings → Delegations | A named substitute for a date range, with "on behalf of" recorded in history. | 🔜 |
| Bulk approve | RFP Approvals / P2P Approvals | Approve many records in one action, skipping failures with a reason. | 🔜 |
| Email / mobile approval | Email approval link + RFP Approvals / P2P Approvals | Signed, single-use approve/return links with a phone-friendly page. | 🔜 |
| SLA targets & aging | RFP Approvals / P2P Approvals + Settings | Target days per stage, with rows highlighted when past target. | 🟡 |

## 5. Visibility, payments & governance

| Feature | Module / Screen | Description | Status |
|---|---|---|---|
| Role dashboards | Dashboard | Requestor, AP Processor and Vendor dashboards. | 🟡 |
| Live dashboard data | Dashboard | Replace the static demo data with scoped queries over RFP and P2P tables. | 🔜 |
| Remaining role dashboards | Dashboard | AP Supervisor, AP Manager, Treasury, Procurement and Warehouse views. | 🔜 |
| Bottleneck dashboard | Dashboard | Slowest stages/approvers, aging and top return reasons. | 🔜 |
| Spend insights & cash forecast | Dashboard | Spend by vendor/category/cost center, plus amounts due in 7/14/30 days. | 💡 |
| Budget context | RFP Approvals / P2P Approvals | Department spend against budget beside the approval. | 🔜 |
| EWT + BIR 2307 | RFP Approvals (Step 4) + Process Accounts Payable | Automatic expanded withholding tax and per-vendor/period 2307 PDFs. | 🔜 |
| Treasury release + payment advice | RFP Approvals (For Treasury) + P2P Approvals | Payment batches, release reference and advice to the payee; bank file later. | 🔜 |
| Audit pack | RFP Requests + P2P Requests | One PDF per record with details, documents, comments and history. | 💡 |
| Governance & data quality | Settings + SAP Logs | Segregation of duties, signed email URLs and missing-code/FX checks. | 🔜 |

## Next

1. Finish the RFP approval actions (parity rebuild, tracked in the roadmap).
2. Add handoff notifications, reminders and escalation.
3. Turn the dashboards from demo data into live queries.
4. Move into Treasury/payment advice, EWT + BIR 2307 and the cross-module links.
