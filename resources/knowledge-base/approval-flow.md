---
title: RFP approval flow
summary: How a Request for Payment moves from the requestor, through the approvers and Accounting, to Treasury.
category: Approvals
order: 1
---

A **Request for Payment (RFP)** is how you ask the company to pay a vendor, or to reimburse you. Every RFP follows the same route. Who approves it in the middle depends on the **request type** and the **amount**.

> **What's live today:** creating, saving and submitting requests (**RFP → My Requests**). The approval screens for each step are being rolled out next. This article already describes the full process.

## The route at a glance

{{block:rfp-route}}

The dashed step is the only part that changes from request to request. The **approval matrix** below decides who signs off there. Every other step is the same for every RFP.

## Step by step

| Step | Who | What they do | What happens next |
|---|---|---|---|
| 1 | **Requestor** | Creates the request, adds line items and supporting documents, then submits. You can **Save draft** and finish later. | Goes to the AP Processor |
| 2 | **AP Processor** | Checks that the request is complete before it reaches the approvers. | Goes to the matrix approvers |
| 3 | **Approvers** | The roles from the approval matrix approve, one after another. | Goes back to the AP Processor |
| 4 | **AP Processor** | Assigns tax codes and decides: **Approve**, **For Revision** or **Cancel**. | Approve → AP Supervisor |
| 5 | **AP Supervisor**, then **AP Manager** | Review the accounting. Both always review, whatever the amount or currency. Status: **For Accounting Approval**. Either can return it to the AP Processor. | Goes to Treasury |
| 6 | **Treasury** | Final step: releases the payment. | Completed |

**Who can be a requestor?** Internal employees. Suppliers can also request a **down payment** (for example 10%); the balance (for example 90%) is then billed through P2P against the purchase order.

## Who approves: the approval matrix

The approvers in Step 3 depend on:

- **Request type.** *Standard* goes through Department Head and FP&A; *Reimbursement* starts with the Line Manager.
- **Amount in PHP.** Foreign-currency requests are converted to PHP using the exchange rate on file, and the matrix uses the PHP amount.
- **Direct to VP.** If this toggle is on, the Direct to VP rules apply instead (see the last table).

An amount **exactly on a limit belongs to the lower tier**. For example, a reimbursement of exactly PHP 10,000.00 needs only the Line Manager.

{{block:rfp-approval-matrix}}

**Tip:** while you fill in a request, the summary bar under the line items shows the **full approval route** for your type, amount and Direct to VP setting. You'll know who will approve before you submit.

## Direct to VP

Turn on **Direct to VP** when a request should skip the usual department chain and go straight to the Division Head (VP):

- **Up to PHP 100,000:** Division Head only.
- **Above PHP 100,000:** Division Head, then Enterprise Controller.

This works for both Standard and Reimbursement requests.

## When a request comes back: For Revision

At Step 4 the AP Processor can send a request back to you as **For Revision**, with remarks explaining what to fix.

- Edit the request and resubmit it from **My Requests → For revision**.
- If the AP Processor marked it **Pre-approved** and you **don't change the amount**, it goes straight back to the AP Processor. You don't need to collect the approvals again.
- If you **change the amount**, or it wasn't pre-approved, it goes through the **approval matrix again**, because the approvers depend on the amount.

The AP Processor can also **Cancel** a request. Cancelled requests are kept for the record but can't continue.

## Request statuses

{{block:rfp-statuses}}

## Good to know

- **Two pages.** **My Requests** lists only the RFPs you raised. **RFP Approvals** is the queue for Admins and Accounting (AP Processor, AP Supervisor, AP Manager): the **For my action** tab (and the number next to the menu item) shows what is waiting for you, and **Current Approver** shows who each RFP is with.

- **After you submit, the request is locked.** You can still open it and see its status and history, but you can only edit it again if it's sent back to you **For Revision**.
- **Due type sets the due date.** **Urgent** is due the next business day and **Standard** in 5 business days, counted from the day you submit. Weekends are skipped.
- **Attach supporting documents to every item** (invoice, receipt or quotation). You can't submit until each item has at least one. Files are stored privately; only people working on the request can open them.
- **Read receipt.** After you upload a PDF or photo of a receipt, invoice or bill in **Supporting Document**, click **Read receipt with AI**. The AI reads it and shows what it found (invoice no., date, amount, currency, details, a suggested sub category and, on a reimbursement, who was paid). Your document is shown on the left of the review, next to what was read, so you can compare each value (zoom in or rotate a photo, or open it in a new tab). Check and correct the values, then click **Apply to item**. Got several receipts? Upload them together (up to 10 files), or as one PDF. Each receipt is listed separately; click **Show on the left** to see it, remove any you don't want, and applying adds one item per receipt. Nothing is filled in until you apply it, and you are still responsible for what you submit. It also warns you about possible duplicates, a date outside the last 6 months, VAT that isn't 12%, or a vendor that doesn't match the RFP.
- **Pre-approved?** If the payment was already approved outside the portal, turn on **Pre-approved** and attach the proof of approval (for example, the approval email). The approval matrix is then skipped.
- **Charge first, then pick the category.** On each item, choose the **cost center** (or enter the **PO no.**) first. Pick the **Category**, then the **Sub category**. The sub category list shows only what fits the cost center or PO: OPEX cost centers (3rd digit 1 or 2) take G/L 6…, MFG cost centers (3rd digit 3 and above) take G/L 5…. A PO no. shows only PO-type sub categories, such as Supplier Down Payment.
- **Each item has its own currency.** Foreign amounts are converted to PHP at the rate on file. The total in PHP is shown live and saved with the request.
- **Who sees your request:** you, the people at each step of its route, and administrators.
- **History:** every status change is recorded with who did it, when, and any remarks. See the **History** panel on the request.

## P2P approvals (coming soon)

Vendor invoices against purchase orders (P2P) have their own route: Vendor → AP Processor → AP Supervisor → SAP. That article will be added when the P2P module goes live.
