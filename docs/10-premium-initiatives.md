# Premium Initiatives (RFP + P2P)

_Last updated: 2026-10-13 · Benchmarks: Stampli (AP Automation, Procurement), Medius, Coupa, SAP Concur_

See [11 – Benchmarks](11-benchmarks.md) for what each product does and for candidate ideas not yet in this list.

**Goal:** make RFP and P2P good enough to compete with the big AP automation tools, without changing Ramcar's process.

## Ground rules

1. **The process stays the same.** These features add no approval steps or statuses. They only make the existing steps faster and safer.
2. **Each feature appears only where it is needed.** Approvers get Approve and Return. The AP team gets the tools.
3. **Settings live in tables**, not enums (reasons, reminder days, delegations, tolerances), so admins change them without a deploy.
4. **RFP first, then P2P.** Each initiative is finished for RFP before any P2P work starts. RFP and P2P are separate systems, so each gets its **own tables** (for example `rfp_receipt_reads` and `p2p_invoice_reads`). Only the UI patterns are shared. **Exception:** comments use one shared polymorphic `comments` table (decision 2026-10-14).
5. **Out of scope:** AI coding, invoice OCR, and syncing with more than one ERP.

We do them **one at a time**, in the order below. Each one ships with Pest tests and updates its row's status.

## Initiative list

| # | Initiative | Module | Wave | Status |
|---|---|---|---|---|
| 0 | Request type names | RFP | — | Done: kept as is |
| 1 | Comments on the record (with @mentions) | Shared | 1 | RFP done · P2P done |
| 2 | Return / cancel reason codes | Shared | 1 | Planned |
| 3 | Notifications at every handoff | Shared | 1 | Planned |
| 4 | Aging + reminders + escalation | Shared | 1 | Planned |
| 5 | Duplicate flagging | Shared | 1 | Planned |
| 6 | Route preview before submit | RFP | 2 | Partly there: the submit modal and the RFP page show the approval route |
| 7 | Bulk approve | Shared | 2 | Planned |
| 8 | Delegation / out of office | Shared | 2 | Planned |
| 9 | Email / mobile approval | Shared | 2 | Planned |
| 10 | Treasury release + payment advice | Shared | 2 | Planned |
| 11 | Withholding tax (EWT) + BIR Form 2307 | Shared | 3 | Planned |
| 12 | Down payment linked to the P2P invoice | RFP ↔ P2P | 3 | Planned |
| 13 | Vendor "Where's my payment?" | P2P | 3 | Planned |
| 14 | Bottleneck dashboard | Shared | 3 | Planned |
| 15 | Budget context for approvers | Shared | 3 | Planned (needs budget data) |

---

## 0. Request type names (decided: keep)

**Decision (2026-10-13):** keep the labels **Standard** and **Reimbursement**.

| Stored value | Label | Covers |
|---|---|---|
| `standard` | Standard | Vendors and other parties outside Ramcar, including supplier down payments |
| `reimbursement` | Reimbursement | Out-of-pocket expenses paid back to an internal employee |

"Vendor Payment" and "Employee Reimbursement" were considered and declined, so the names stay familiar to legacy users.

## 1. Comments on the record

**Why:** today "Return" carries only a remark. Questions go to email or chat, and the RFP comes back incomplete again.

**RFP (done 2026-10-13):**
- Shared table `comments` (commentable record, user, rich-text body, attachments). Model `App\Models\Comment`; a record opts in with the `HasComments` trait and defines `mentionableUsers()`, `commentSubject()` and `commentUrlFor()`. RFP's morph alias is `rfp`.
- **Chat bubble (2026-10-14):** a floating bubble at the bottom right of every RFP and P2P page (Livewire `App\Livewire\CommentsChat`, added to a page with the `HasCommentsChat` trait). On a record page (RFP view/edit, P2P AP and GR screens with `?invoice=`) it opens that record's thread; on list and create pages and the Dashboard (`/hub`) it opens an **inbox** of the user's conversations, newest first, with unread counts (`App\Services\Comments\CommentInbox`). Each RFP and P2P table row has a chat icon (`OpenCommentsAction`) with its unread count that opens the row's thread; on Escalations the thread is the escalation's invoice. The bubble checks for new messages every 15 seconds and shows a red unread badge while closed (table `comment_reads`). Posting from the bubble or from **Add comment** goes through `App\Services\Comments\PostComment`.
- A **Comments** section on the RFP page, both in My Requests and in RFP Approvals, with an **Add comment** button: rich text plus up to 5 PDF/image attachments.
- Type **@** to mention someone. Only people who can open the RFP can be mentioned: the requestor and Admin/AP roles. Business approvers are added when they get an RFP screen.
- Mentioned users get an in-app notification with a link to the RFP. Authors are never notified about their own comments.
- A comment never changes the status. Return and Cancel stay separate actions.

**P2P (done 2026-10-14):** `Invoice` uses `HasComments` (morph alias `p2p_invoice`). Staff (`RoleName::p2pStaff()`: Admin, AP Processor, AP Supervisor, Procurement, Warehouse roles) can open any invoice's thread; a vendor only the invoices they submitted. Staff messages are **Internal** by default (`comments.is_internal`), hidden from vendors and never notified to them; untick Internal to message the vendor. Vendors' own messages are always visible.

## 2. Return / cancel reason codes

- A `return_reasons` table (for example: missing attachment, wrong amount, wrong charging, wrong payee, other), managed in Settings.
- Return and Cancel ask for a reason plus remarks. The reason is stored in History.
- Feeds the dashboard ("why RFPs come back").

## 3. Notifications at every handoff

- In-app (Filament database notifications) and email when an RFP or invoice reaches you, is returned to you, is commented on with your @mention, or is paid.
- One notification per handoff. Users can mute email but not in-app.

## 4. Aging + reminders + escalation

- Every queue shows **days at the current stage**. Rows are highlighted past the stage's target days.
- A scheduled job sends a reminder after X days and escalates to the AP Processor after Y days.
- The X and Y values per stage live in a settings table.

## 5. Duplicate flagging

- Warn when **payee + amount + invoice/OR no.** matches another open or paid RFP or P2P invoice.
- Shown to the requestor on submit (warning only) and to the AP Processor as a panel on review.
- It never blocks. AP decides.

## 6. Route preview before submit (RFP)

- Before submitting, the requestor sees the approvers their RFP will go to, worked out from the matrix (type + PHP amount + Direct to VP), for example "Line Manager → Department Head".

## 7. Bulk approve

- Select several rows in a queue and approve them in one action. Each RFP is checked on its own, and the ones you can't approve are skipped with a reason.
- Meant for the AP Supervisor, the AP Manager and matrix approvers with many small items.

## 8. Delegation / out of office

- An approver names a substitute and a date range in a `delegations` table.
- While it's active, the substitute sees the approver's items in "For my action". History records "approved by X on behalf of Y".

## 9. Email / mobile approval

- The handoff email has secure, single-use **Approve** and **Return** links (signed URLs that expire).
- An approval page that works well on a phone. Aimed at the Department Head, President and ExCom.

## 10. Treasury release + payment advice

- Treasury groups items into payment batches by due date.
- On release Treasury records the check no. or bank reference and the date. That completes the record ("Payment Released").
- A payment advice email goes to the employee or vendor. Bank file export comes later.

## 11. Withholding tax (EWT) + BIR Form 2307

- Compute expanded withholding tax from the tax code at AP Processing (the net of withholding tax total already exists).
- Generate **BIR Form 2307** per vendor and period as a PDF.
- A Philippine-specific advantage the global tools handle poorly.

## 12. Down payment linked to the P2P invoice

- A down payment RFP can reference a PO number. When the vendor later submits the P2P invoice for that PO, the paid DP is **deducted automatically** and the two records link to each other.

## 13. Vendor "Where's my payment?" (P2P)

- On the vendor portal, each invoice shows its status, current stage and expected payment date.
- Meant to cut vendor follow-up calls to AP.

## 14. Bottleneck dashboard

- Average days per stage, slowest approvers, aging buckets, top return reasons, for both RFP and P2P.

## 15. Budget context for approvers

- Approvers see department spend against budget next to the request. Waits on a budget data source.
