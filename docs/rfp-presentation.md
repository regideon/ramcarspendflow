# The New RFP — Ramcar AP

_Presentation notes · Last updated: 2026-10-23_

> Each `---` below is one slide. Status tags: **✅ Built** (ready to demo) · **🔜 Coming** (planned, not yet built).
> Keep this file current: when a feature ships, move it from "Coming" to its slide and update the date.

---

## 1. Why a new RFP?

The legacy RFP Portal works, but:

- Accounting works in **two separate systems** (RFP Portal and P2P).
- Questions and corrections happen **over email and chat**, outside the RFP.
- Requestors type every receipt **by hand**, and mistakes are found late.
- The approval matrix and due dates are **fixed in code**.

**Ramcar AP** puts RFP and P2P in **one app**, keeps **our approval process exactly as approved**, and adds the tools the big AP platforms have.

---

## 2. Same process, better tools

Our approval flow stays the same:

**Requestor → AP Processor → Matrix approvers → AP Processor → AP Supervisor → AP Manager → Treasury → Payment released**

- Two request types, as today: **Standard** (vendors and other parties outside Ramcar, including supplier down payments) and **Reimbursement** (internal employees).
- The 2026 approval matrix by type and amount, including **Direct to VP** and **Pre-approved**.
- Nothing new to learn about *who* approves. What changes is *how fast and how safely* it happens.

---

## 3. ✅ AI receipt reading

**Upload a receipt and click "Read receipt with AI".**

- Reads the **invoice no., invoice date, description, currency and amount**, plus the **vendor name and TIN**.
- **Suggests the sub category** (and so the G/L account).
- **Flags possible duplicates:** "Invoice no. 12345 for this amount is already on RFP-2026-000118."
- The requestor **reviews and corrects** everything before applying it. The AI never submits on its own.
- **Side-by-side check:** the review opens near full screen with the receipt on the left (zoom and rotate photos, scroll PDFs) and what was read on the right, so each value is compared with the document before it is applied.
- **Several receipts at once:** upload up to 10 files, or one PDF with several receipts. The AI finds each receipt and adds **one item per receipt**, each with its own document and the same charging. A **Show on the left** link jumps to that receipt's file and page.
- Works with PDFs and photos (JPG/PNG). A receipt already read is not read again.

**Benefit:** less typing, fewer typos, and duplicate payments caught before they reach AP.

---

## 4. ✅ A smarter request form

- **Excel-like item table:** fast to fill, compact, familiar.
- **Mass upload:** fill an Excel template (master data included) and upload all items at once.
- **Charging first:** pick a cost center or PO no. first. The app then shows **only the G/L accounts allowed** for it (OPEX vs MFG), so wrong charging is stopped at the source.
- **Multi-currency per line:** each line keeps its own currency, converted to PHP automatically.
- **Live totals and approval route:** the requestor sees the PHP total and **who will approve it** before submitting.
- **Automatic due dates** from the due type, skipping weekends. This fixes the legacy Saturday due-date problem.
- **Supporting documents per line**, and proof of approval required when Pre-approved is ticked.
- **Checks before submit:** an incomplete RFP stays a draft instead of bouncing back from AP.

---

## 5. ✅ Smart comments with @mentions

**Conversations live on the RFP, not in email.**

- Every RFP has a **Comments** section for the requestor and AP.
- A **chat bubble** sits at the bottom right of every RFP page. On an RFP it opens that RFP's chat; on the lists it opens your **inbox** of conversations. A **red badge** shows how many new messages you haven't read.
- Every row in My Requests and RFP Approvals has a **chat icon** with its unread count, so you can answer without opening the RFP.
- Type **@** to mention someone. They get a **notification** with a link straight to the RFP.
- Attach PDFs or photos to a comment, for example a missing OR.
- Only people who can open the RFP can be mentioned.
- A comment **never changes the status**. Return and Cancel stay formal actions.

**Benefit:** questions answered in minutes, fewer returns, and a complete record of every decision.

---

## 6. ✅ Approval queues that match the flow

**RFP Approvals** (Admin and AP):

- **For my action:** everything waiting for *you*, with a count badge in the menu.
- **One tab per step**, in flow order: **1. AP Processor · 2. Matrix Approval · 3. AP Processor · 4. AP Supervisor · 5. AP Manager · 6. Treasury**.
- Then the outcomes: **Payment Released · Returned to Requestor · Rejected · Cancelled · All**.
- Search by RFP no., **invoice no.**, payee or requestor. "Currently with" shows who holds each RFP.
- **Urgency chips** above the tabs, one click to filter: **Overdue · Due today · Urgent**, plus **Back on my desk** (AP Processor), **Waiting 3+ days** (AP Supervisor / Manager), **Foreign currency** (AP Manager) and **Stuck 5+ days** (Admin).
- A **Due** column that turns **red when overdue** and **amber when due today**.
- **My Requests** gets its own chips: **Returned to me · Drafts not submitted · In progress · Paid this month** (with PHP totals).
- **New look (RFP › RFP Approvals):** the step tabs read as a route (1 → 6 with arrows), one **Filters** panel (Company, Vendor, Urgency, RFP type, Status, dates) shows each filter in use as a **removable chip** with **Clear all**, a **Columns** menu hides the columns you don't need, and **Export CSV** downloads what you see. A **Guide** button explains each part. The old list stays under RFP (OLD) for comparison.
- **Mass approval (approver portal, /my/approvals):** approvers tick several RFPs and approve them in one go, with a **running peso total**. High amounts (**PHP 200,000 and above** by default) can't be ticked and must be approved one by one, and one batch can add up to **PHP 500,000** at most: once the total is reached, the other RFPs are locked with a note explaining why. *(Preview: the portal still runs on sample data.)*
- **New RFP screen (opened from the new list):** a 7-step **progress tracker** at the top, the request overview with the **total amount** highlighted, items, tax entries, history and comments side by side (with **Add comment**), and a **sticky decision bar** at the bottom of the screen with your step's buttons (Endorse / Process tax codes / Review and approve / Final review / Release payment on the right, Return and Cancel on the left), so you never scroll back up to act.

---

## 7. ✅ Track every request

- **Progress tracker** on each RFP: New request → AP review → Approval → AP processing → Accounting approval → Treasury → Completed.
- **Full history:** who did what, when, with remarks.
- **My Requests** shows each requestor only their own RFPs.
- The list shows **who created** each RFP and **who last updated** it, under the Created and Last update dates.

---

## 8. ✅ Dashboards by role

**Requestor:** requests in flight, action needed, upcoming due dates, how long each stage is taking, spend over time.

**AP Processor:** work queue, queue aging, RFPs back on the desk, tax summary, and "keeping up" (in vs out).

**New look + built-in User Guide:** the AP Processor dashboard is redesigned (premium, cleaner layout). A **Guide** button beside the Messages bubble walks users through each part of the page, step by step, in plain language.

---

## 9. ✅ Run by the business, not by code

Admins change these in **Settings**, with no developer needed:

- **Approval workflow:** add, remove or reorder the stages from AP review to Treasury, with a **live flow diagram** showing who approves in each company and warning where nobody would. Safety rules keep AP review first and Treasury last, and requests already submitted keep their route.
- **Approval roles by company:** the same person can approve for several companies, or hold different roles in different companies.
- **Approval matrix:** tiers, amounts and approver roles, with a **visual matrix at a glance**: each tier's approver chain, who holds each role in a chosen company, and the tier a sample amount falls in.
- **Due types:** processing days.
- **Bulk approval limits:** the highest amount per RFP and the highest total per batch for mass approval.
- **Expense categories and sub categories**, mapped to G/L accounts.
- **Master data:** companies, vendors, cost centers, G/L, tax codes, exchange rates.

Also built: the **legacy RFP Portal data is imported**, so history is not lost, plus an in-app **Knowledge Base** and an activity log.

---

## 10. 🔜 Coming next

| Feature | What it does |
|---|---|
| **Approval actions** (Steps 3–6) | Approve, return, cancel and reject at the remaining steps. **Built: Step 2 AP review** (Endorse / Return for revision) **Step 4 AP processing** (tax codes per line with live VAT and withholding, zero-variance check, Return for revision, Cancel) **Step 5 AP Supervisor** (review or correct the tax codes, Return to AP Processor) **Step 6 AP Manager** (final review, IDS / Non-IDS, Complete, Cancel, Return) and **Treasury** (Release payment → Completed), with in-app notifications |
| **Return reason codes** | Standard reasons for returns and cancellations, so we can see why RFPs come back |
| **Email + in-app notifications** | At every handoff, plus "Payment released" |
| **Aging, reminders, escalation** | "3 days with Dept Head", automatic reminders, escalation to AP |
| **Bulk approve** | Approve many RFPs in one go |
| **Delegation / out of office** | An approver names a substitute while on leave |
| **Approve from email or phone** | Secure one-click approve for busy approvers |
| **Treasury release + payment advice** | Payment batches by due date, release reference, payment advice email |
| **EWT + BIR Form 2307** | Automatic withholding tax and 2307 generation |
| **Bottleneck dashboard** | Days per stage, slowest steps, top return reasons |

---

## 11. How we compare

| Capability | Stampli / Medius / Coupa | Ramcar AP |
|---|---|---|
| AI document reading | ✅ | ✅ |
| Comments with @mentions on the record | ✅ | ✅ |
| Rule-based approval routing | ✅ | ✅ (our 2026 matrix) |
| Role dashboards and work queues | ✅ | ✅ |
| Duplicate flagging | ✅ | ✅ when reading receipts · 🔜 at submit and review |
| Reminders, delegation, mobile approval | ✅ | 🔜 |
| Philippine EWT + BIR 2307 | Limited | 🔜 |
| Built around Ramcar's exact process | ✗ (we would adapt to them) | ✅ |
| RFP and P2P in one app | Separate modules / licenses | ✅ |

---

## 12. Summary

- **Same Ramcar process.** No new approval steps.
- **Less manual work:** AI receipt reading, mass upload, automatic due dates and routes.
- **Fewer mistakes:** charging-first rules, duplicate flags, checks before submit.
- **Faster answers:** @mention comments on the RFP.
- **Clear visibility:** queues per step, progress tracker, role dashboards.
- **Next:** approval actions, notifications, reminders, and then P2P on the same foundation.
