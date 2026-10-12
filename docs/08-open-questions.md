# Open Questions

When a question is answered, move it to the [decision log](07-decisions.md).

## Parked

| # | Question | Owner | Notes |
|---|---|---|---|
| P1 | Which withholding tax **type** does each WHT code belong to? | Tax | Legacy data never linked them; `withholding_tax_codes.withholding_tax_type_id` is empty |
| P2 | **SAP integration** (master data, P2P PO/GR, RFP posting) | Team | Deferred: generic demo data for now. Includes M1 and R5. |

## Master data

| # | Question | Why it matters |
|---|---|---|
| M1 | _(Deferred with P2)_ Which SAP OData endpoints exist for **vendors** (LFA1/LFB1), **G/L accounts** (SKA1/SKB1), **cost centers** (CSKS) and **FX rates** (TCURR)? Companies already have `ZCDS_FI_T001`. | Decides which master data is synced from SAP and which is imported once then maintained in the portal |

## RFP (needed before Phase 2)

| # | Question | Why it matters |
|---|---|---|
| R1 | What exactly does the AP Processor do at **Step 2** (first review)? Can they return or cancel there? | Defines Step 2 actions and statuses |
| R2 | At Step 3, can an approver **return**, and to whom (Requestor or AP Processor)? (Reject exists: status **Rejected**, as in legacy.) | Status machine |
| R3 | Is a **specific person** picked for each matrix role, or can **any user with the role** (in the RFP's company) approve? (The data model supports both: `current_approver_role` + optional `current_approver_id`.) | Approver assignment and notifications |
| R4 | What happens at **Treasury** (Step 6): release, payment date, check/transfer details? Is there an SAP posting after it? | Final status and SAP integration |
| R5 | **RFP SAP integration** (in progress: being confirmed with the team). What comes from SAP, and what is sent to SAP: AL11 file, like the planned P2P change, or another interface? | Phase 2.13, and whether RFP master data must be synced from SAP |
| R6 | Supplier down payment: is the DP % fixed or entered? How is the 90% balance linked to the P2P invoice? | Phase 2.2 / 3.x link |
| R8 | Confirm the rest of the status list in [RFP flow §6](03-rfp-approval-flow.md#6-statuses) (Step 5 is settled: For Accounting Approval) | Status machine |

## P2P (needed before Phase 3)

| # | Question | Why it matters |
|---|---|---|
| X1 | Does P2P now need **AP Manager** or **Treasury** steps, or does it stay Processor → Supervisor → SAP? | Approval chain |
| X2 | Confirm the SAP write side is the **AL11 file interface** (plan 261001) | Phase 3.5 |
| X3 | Which legacy statuses are still needed (several are "reserved"/"legacy" in the master doc)? | Simplify the status enum |
