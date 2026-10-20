# Benchmarks: Best-in-Class Spend Tools

_Last updated: 2026-10-14_

The products we compare Ramcar Spend Flow (RFP + P2P) against, and the ideas we borrow from each. Use this doc to decide **what to build next and how good it needs to be**. The build tracker itself is [10 – Premium initiatives](10-premium-initiatives.md).

> **How to use it:** before designing a feature, look at how the products below handle it. Copy the idea, not the complexity. Ramcar's approval process never changes to match a product.
>
> Stampli's pages were reviewed on 2026-10-13. Other entries are from product knowledge, so check their current sites before quoting them in a presentation.

## 1. At a glance

| Product | Category | Best benchmark for | Module |
|---|---|---|---|
| [Stampli – AP Automation](https://www.stampli.com/ap-automation-platform/) | AP automation | AP team workflow, comments on the record, approver experience | RFP + P2P |
| [Stampli – Procurement](https://www.stampli.com/procurement/) | Intake-to-procure | Request intake, routing by rules, budget context, request → PO → receipt → invoice in one thread | RFP intake, P2P |
| [Medius](https://www.medius.com/) | AP automation | Invoice next to PO/GR (side by side), matching and exceptions | P2P |
| [Coupa](https://www.coupa.com/) | Business spend management | Enterprise approval chains, amount thresholds, delegation, tolerances | RFP matrix, P2P match |
| [SAP Concur](https://www.concur.com/) | Expense + invoice | Employee reimbursements, receipt handling, policy checks | RFP Reimbursement |
| [Zip](https://ziphq.com/) | Intake & approvals | Simple request forms that adapt by category and amount | RFP intake |
| [Tipalti](https://tipalti.com/) | Payables + payments | Supplier onboarding, bank detail checks, payment runs, tax forms | Treasury, vendors |
| [BILL](https://www.bill.com/) / [AvidXchange](https://www.avidxchange.com/) | AP + payments | Payment batching, payment status for vendors | Treasury, P2P vendor portal |
| [Basware](https://www.basware.com/) | E-invoicing + AP | Invoice matching rules, compliance, audit trail | P2P |
| [Ramp](https://ramp.com/) / [Brex](https://www.brex.com/) / [Spendesk](https://www.spendesk.com/) | Spend platforms | Modern, simple UX; reminders; insights | Whole app (UX) |
| [Procurify](https://www.procurify.com/) / [Precoro](https://precoro.com/) | Mid-market P2P | Budgets, PO and receiving for companies our size | P2P |

**Primary benchmarks:** **Stampli** (AP and procurement experience), **Coupa** (approval logic), **SAP Concur** (reimbursements), **Medius** (P2P matching screen).

## 2. What Stampli does, and where we stand

From Stampli's AP Automation and Procurement pages:

| Stampli capability | Ramcar Spend Flow | Initiative |
|---|---|---|
| Questions, documents and decisions kept **on the record** | ✅ Comments with @mentions (RFP and P2P) | #1 |
| Routing by entity, amount, department, vendor, request type | ✅ 2026 approval matrix by type + amount, Direct to VP, Pre-approved | Roadmap 2.4 |
| Trays, reminders, clear task ownership | ✅ "For my action" + tabs per step, "Currently with" · 🔜 reminders | #4 |
| Mobile approvals | 🔜 | #9 |
| Reroute approvals / exceptions in context | 🔜 Delegation | #8 |
| AI reads invoices, flags duplicates | ✅ AI receipt reading with duplicate warning · 🔜 duplicate check at submit and review | #5 |
| AI suggests coding | Partly: AI suggests the sub category (→ G/L) | — |
| Role-based access, approval history, change tracking | ✅ Roles, History, activity log | — |
| Request intake that adapts by category, department, amount | ✅ Charging-first rules, per-type form | — |
| Budget impact shown to approvers | 🔜 Needs a budget source | #15 |
| Request → PO → receipt → invoice kept connected | ✅ P2P starts from synced GRs · 🔜 RFP down payment linked to the P2P invoice | #12 |
| Visibility of bottlenecks and commitments | Partly: role dashboards · 🔜 bottleneck dashboard | #14 |
| ERP stays the system of record | Same principle: SAP is the source for P2P purchasing data | — |
| Configurable queues for central and decentralized AP | ✅ Queues per step and per role | — |

## 3. Best practice by step

What the leading tools do at each step of **our** flow, and what we take from them.

### RFP

| Step | Best practice (who does it well) | What we take |
|---|---|---|
| 1. Requestor | Smart intake forms, route preview, policy hints (Zip, Stampli Procurement); receipt capture (Concur) | Route preview ✅, AI receipt reading ✅, mass upload ✅, policy hints on the form |
| 2. AP Processor review | Completeness checks, duplicate detection, questions on the record (Stampli) | Review checklist, duplicate panel, comments ✅ |
| 3. Matrix approvers | One-click email/mobile approval, delegation, reminders, budget context (Coupa, Stampli) | #9, #8, #4, #15 |
| 4. AP Processor processing | Tax coding helpers, totals that must balance (Stampli, Basware) | Process Tax Codes page with a fast tax grid, EWT + 2307 (#11) |
| 5. AP Supervisor / Manager | Bulk approve, exception-only review (Coupa, Stampli) | #7, return with reason (#2) |
| 6. Treasury | Payment batches, cash forecast from due dates, payment advice (Tipalti, BILL) | #10, cash forecast (candidate C3) |
| 7. Payment released | Payment status visible to the payee (BILL, AvidXchange) | Payment advice email (#10) |

### P2P

| Step | Best practice (who does it well) | What we take |
|---|---|---|
| Vendor submission | Vendor portal with status tracking (BILL, Tipalti) | Choose synced GRs ✅, "Where's my payment?" (#13) |
| Matching | Invoice beside PO/GR, line-level match, tolerances (Medius, Coupa, Basware) | Side-by-side screen ✅, tolerance rules in a table |
| Exceptions | Exception queue with comments and owners (Stampli, Medius) | Comments ✅, escalations ✅, reasons (#2) |
| AP approval | Bulk approve, reminders (Coupa) | #7, #4 |
| Payment | Payment runs and remittance advice (Tipalti, BILL) | #10 |

## 4. Candidate initiatives (not yet in the tracker)

Ideas from the benchmarks that are **not** in [10 – Premium initiatives](10-premium-initiatives.md) yet. Promote one by adding it to that tracker.

| # | Idea | Seen in | Why it matters for Ramcar |
|---|---|---|---|
| C1 | **Vendor bank detail change control:** a change of bank account needs approval, is flagged on the next payment and is logged | Tipalti, Stampli | The most common payment fraud is a fake "new bank account" email |
| C2 | **Vendor self-onboarding:** the vendor fills in its details, TIN and bank, and AP approves | Tipalti, Coupa | Fewer typos in vendor master data, less AP data entry |
| C3 | **Cash requirements forecast for Treasury:** amounts due by date from approved RFPs and invoices | Tipalti, BILL | Treasury plans funding before the due date |
| C4 | **Policy hints on the request form:** for example "Meals above ₱X need an attendee list" | Concur, Zip | Fewer returns at AP review |
| C5 | **Exception-only review:** clean items (matched, complete, no flags) go to a fast lane | Coupa, Medius | AP spends time only where there is a problem |
| C6 | **Audit pack export:** one PDF per RFP or invoice with the details, documents, comments and history | Stampli, Basware | Ready answers for internal and external audit |
| C7 | **Spend insights:** spend by vendor, category, cost center and month | Ramp, Coupa | Management sees where money goes |

## 5. Where we will not copy them

- **No new approval steps** to match a product. Ramcar's flow ([03](03-rfp-approval-flow.md), [04](04-p2p-flow.md)) is fixed.
- **No multi-ERP connectors.** We only need SAP.
- **No heavy AI auto-coding.** AI suggests, people decide. This is how our receipt reading already works.
- **No per-module licensing feel.** RFP and P2P share one login, one look and one set of master data.
