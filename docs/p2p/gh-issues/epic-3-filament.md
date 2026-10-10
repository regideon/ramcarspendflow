# Epic 3 — Filament

**Labels:** `P2P` · **Phase:** 3 · **Depends on:** Epic 1 (+ Epic 2 for actions)

Filament 5 resources, custom pages and the Medius-style side-by-side viewer
([../04-filament-ui.md](../04-filament-ui.md)). **Checker screen is blocked (B1).**

| ID | Title | Labels | Depends on |
|---|---|---|---|
| P2P-3.1 | P2P nav group + P2P Settings cluster | P2P, RFP | 1.4 |
| P2P-3.2 | Purchase Orders resource (+ Sync from SAP) | P2P | 1.7 |
| P2P-3.3 | Goods Receipt resource | P2P | 1.7 |
| P2P-3.4 | Invoices / Accounts Payables resource | P2P | 1.7, 2.2 |
| P2P-3.5 | P2P Approvals resource (badge) | P2P | 2.2 |
| P2P-3.6 | Escalations resource | P2P | 2.7 |
| P2P-3.7 | Bulk Goods Receipt page (Vendor) | P2P | 2.2 |
| P2P-3.8 | Bulk Accounts Payable page (AP Processor) | P2P | 2.2, 2.4 |
| P2P-3.9 | Medius side-by-side viewer | P2P | 3.7, 3.8 |
| P2P-3.10 | P2P Settings resources | P2P | 1.4 |
| P2P-3.11 | Exporter + Blade components | P2P | 3.4 |
| P2P-3.12 | Access tests for every screen | P2P | 3.2–3.10 |

---

### P2P-3.1 — P2P nav group + P2P Settings cluster
- **Labels:** `P2P`, `RFP` (panel nav is shared)
- **Scope:** add `P2P` to `HubPanelProvider::navigationGroups()`; create `P2P Settings` cluster under Settings.
- **Acceptance:** groups render for the right roles; RFP group untouched.

### P2P-3.2 — Purchase Orders resource
- **Scope:** `PurchaseOrderResource` on the mirror PO model; "Sync from SAP" header action (Vendor).
- **Acceptance:** vendor sees own POs; sync shows pending/complete notification.

### P2P-3.3 — Goods Receipt resource
- **Scope:** GR resource, `shouldRegisterNavigation = false`, reached via PO link / redirect.
- **Acceptance:** reversed GR shows replacement redirect; no pricing columns to vendor.

### P2P-3.4 — Invoices / Accounts Payables resource
- **Scope:** `InvoiceResource`; nav label **Invoices** (Vendor) / **Accounts Payables** (staff); list rules per legacy.
- **Acceptance:** role-correct label + scoping; search by invoice/ref no.

### P2P-3.5 — P2P Approvals resource
- **Scope:** queue of items actionable by the current user with a **navigation count badge**; AP Processor now, AP Checker later.
- **Acceptance:** badge count correct; other roles 403.

### P2P-3.6 — Escalations resource
- **Scope:** Procurement/Warehouse queue; advance/resolve hops; Reverse & re-GR action.
- **Acceptance:** route hops honored; custom action replaces Advance.

### P2P-3.7 — Bulk Goods Receipt page (Vendor)
- **Scope:** select GR lines/quantities, invoice date, reference no(s), invoice PDF; Submit to AP / Escalate.
- **Acceptance:** validation (ref, attachment, qty, invoice date); partial supported.

### P2P-3.8 — Bulk Accounts Payable page (AP Processor)
- **Scope:** review submission, assign tax/WHT, see VAT/EWT summary; Submit / Escalate / Return to Vendor.
- **Acceptance:** processor branch only; checker branch blocked (B1).

### P2P-3.9 — Medius side-by-side viewer
- **Scope:** invoice PDF next to PO/GR lines with per-line 3-way-match badge; Livewire 4 components.
- **Acceptance:** quantities/prices/amounts comparable without navigation.

### P2P-3.10 — P2P Settings resources
- **Scope:** SLOC/material classification (+ users), Items, Delivery Types, Escalation Reasons, Points of Contact.
- **Acceptance:** admin-only; deactivate not delete; unique validation on SAP codes.

### P2P-3.11 — Exporter + Blade components
- **Scope:** `P2pInvoiceExporter`; timeline, summarize, PDF-link, attachments columns.
- **Acceptance:** export tracks in `exports`; components render in form + infolist.

### P2P-3.12 — Access tests for every screen
- **Scope:** each screen returns 200 for allowed role, 403 for others.
- **Acceptance:** Pest feature tests green.
