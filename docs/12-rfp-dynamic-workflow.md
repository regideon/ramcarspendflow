# RFP Dynamic Approval Workflow

_Last updated: 2026-10-23 · Status: **engine and settings built**; approve/return/reject buttons on RFP Approvals still to come (roadmap 2.5–2.9)_

**Summary:**
- The whole chain after the requestor is **data**: one **global workflow** of ordered stages, edited by Admins under **RFP Settings → Approval Workflow**.
- One stage kind, **Approval matrix**, expands into the matching tier of the existing matrix ([03](03-rfp-approval-flow.md) §3).
- On submit, the route is **copied onto the RFP**, so later workflow or matrix edits never change RFPs in flight.

Default (seeded by `WorkflowSeeder`): **Requestor → AP Processor → Matrix approvers → AP Processor → AP Supervisor → AP Manager → Treasury**

## 1. Decisions (2026-10-23)

| Question | Decision |
|---|---|
| Different workflows per request type / company / amount? | **No: one global workflow.** The matrix already varies by type and amount |
| Who edits the workflow? | **Admins only** |
| Block a requestor from approving their own RFP (when they hold an approver role)? | **No.** They approve like anyone holding the role |
| Approvers by person or by role? | **Roles.** One person can hold several roles (e.g. AP Supervisor and AP Manager); they then approve each stage separately |
| Are roles the same in every company? | **No: roles are held per company** (`company_role_user`). Juan can be AP Processor for 1000 and 2000; Pedro can be Line Manager in 1000 but Department Head in 3000 |

## 2. Approval roles by company

- Set on **Users → Approval roles by company**: each row is one role plus the companies the person holds it in.
- The **General roles** field only offers roles that don't depend on the company (Admin, Requestor, Vendor, Procurement, Warehouse…).
- `company_role_user` is the source of truth for routing:
  - The engine (`actionableStep`) and `Rfp::awaiting()` only match a role the user holds **in the RFP's company**.
  - AP users see, in RFP Approvals, only the RFPs of companies they hold a role in. Admins see all.
- Spatie roles still grant the screens. `User::syncCompanyRoles()` keeps them in step: a user has an approval role's screens while they hold that role in at least one company.
- Existing users were carried over by the migration: their approval roles × their companies (or their home company).

## 3. The Approval Workflow page

- **Flow diagram on top:** Requestor → stages → Paid.
  - The matrix stage expands for a sample request (**Preview as** company / type / amount / Direct to VP).
  - Each role shows **who holds it in that company**. ⚠ **Nobody in this company** flags a stage where requests would get stuck.
  - Badges show return-to-requestor, return-to-stage, reject and cancel.
  - The diagram follows unsaved edits. Clicking a stage scrolls to its settings.
- **Stage settings below:** the reorderable list, saved with **Save workflow**.

## 4. Stage kinds (`App\Enums\RfpStageKind`)

| Kind | Who acts | Notes |
|---|---|---|
| Approval | One configured role | Any RFP role, e.g. AP Processor, AP Supervisor, Controller |
| Approval matrix | The tier's roles | Same step number = parallel. Skipped when Pre-approved |
| AP processing | Configured role (AP Processor) | Booking entries and taxes |
| Payment release | Configured role (Treasury) | Always last |

Each stage also sets:
- the **RFP status** shown while the RFP is at that stage, so the existing queue tabs, chips, dashboards and progress tracker keep working;
- a **label**;
- **can return to requestor**;
- **can return to stage no.**;
- **can reject**;
- **can cancel**.

## 5. Rules enforced when saving (`WorkflowStage::problemsWith`)

- Ends with exactly **one payment release** stage.
- Exactly **one AP processing** stage.
- At most **one approval matrix** stage.
- The **first stage is an AP review** (AP Processor, AP Supervisor or AP Manager).
- Every non-matrix stage has a role.
- "Return to stage" only points to an **earlier, non-matrix** stage.

## 6. Data

| Table | Holds |
|---|---|
| `rfp_workflow_stages` | The global workflow: `position`, `kind`, `role`, `status`, `label`, `can_return_to_requestor`, `return_to_position`, `can_reject`, `can_cancel` |
| `rfp_approval_steps` | Each RFP's own route: one row per stage, or per matrix role. Columns: `round`, `sequence` (shared = parallel), `state` (pending / active / approved / returned / rejected / cancelled / skipped / superseded), who acted, when, remarks |
| `rfps.approval_round` | The round in progress (+1 on each resubmission) |
| `company_role_user` | Which approval role a user holds in which company |
| `rfps.routed_php_amount` | The PHP total the matrix approved. Cleared when AP does not keep the matrix approval |

`rfps.current_approver_role` / `current_approver_id` are still set to the active step, so existing queries keep working. `Rfp::awaiting()` also matches any active parallel step.

## 7. Engine (`App\Services\Rfp\ApprovalEngine`)

| Method | Effect |
|---|---|
| `start` (via `Rfp::submit`) | Copies the workflow, expands the matrix, activates the first stage |
| `approve` | Closes the user's active step. Moves on when every parallel step is approved. After the last step → **Completed** |
| `returnToRequestor($remarks, keepMatrixApproval)` | **For Revision**. Without `keepMatrixApproval`, the resubmission goes through the matrix again |
| `resubmit` (via `Rfp::submit` on a returned RFP) | New round. Stages before the matrix that already approved are skipped. The matrix is skipped when it approved the same amount and AP kept it |
| `returnToStage($remarks)` | Runs the target stage and every later stage again (e.g. AP Supervisor → AP Processing) |
| `reject` / `cancel` | Final; remarks required |
| `actionableStep` | The step a user may act on now (for the coming buttons and policies) |
| `preview` | The route shown on the request form and the Submit dialog |

Every action writes an `rfp_histories` row with an action ("Approved (AP Supervisor)", "Returned to AP Processor", …).

## 8. Still to do

1. **Buttons on RFP Approvals**, one step at a time, built on `actionableStep()` (roadmap 2.5–2.9):
   - ✅ **Step 2 AP review** (`ApReviewActions`), matching legacy: **Endorse** (optional remarks) or **Return for revision** (AP remarks required). No reject or cancel. The requestor sees a "Returned for revision" callout and gets an in-app notification. The next approvers in the RFP's company are notified when it reaches them.
   - **Matrix parked:** while `config('rfp.auto_approve_matrix')` (`RFP_AUTO_APPROVE_MATRIX`, default on) is set, the matrix steps are auto-approved and logged ("Matrix auto-approved").
   - ✅ **Step 4 AP processing** (`ApProcessingActions`, `RfpTaxProcessing`, `RfpTaxCalculator`), matching legacy "Process Tax Codes":
     - per line: **input tax** + **withholding tax** (type EI);
     - VAT / net of VAT / withholding / net of WHT worked out live (gross is VAT-inclusive);
     - AP may re-split the gross between lines of a currency (**variance must be 0**);
     - totals per currency incl. **total net of WHT**.
     - Saving sends it to the AP Supervisor. **Return for revision** (with "keep the matrix approval") and **Cancel** (reason required) are `StepActions`.
     - The booked **Tax entries** show on the RFP for the next approvers.
   - ✅ **Step 5 AP Supervisor** (`ApSupervisorActions`): **Review and approve** opens the same tax form filled with the AP Processor's codes; the Supervisor may change them, then it goes to the AP Manager. **Return to AP Processor** (`StepActions::returnToStage`, remarks required) re-runs AP processing with the booked codes kept. No cancel, reject or return to the requestor.
   - ✅ **Step 6 AP Manager** (`ApManagerActions`), the final accounting gate:
     - **Final review**: the same tax form with the **input tax locked** (withholding tax may change; enforced server-side) plus the **payment type IDS / Non-IDS** (`rfps.is_ids`, legacy `ids_type`).
     - "Complete and send to Treasury".
     - Legacy's "Final Status: Cancelled" is the separate **Cancel request** button (the default workflow now lets the AP Manager cancel).
     - **Return to AP Processor** as at Step 5.
   - ✅ **Treasury** (`TreasuryActions`), unlike legacy (where the AP Manager completes the RFP):
     - **Release payment** (may flip IDS / Non-IDS, optional remarks) → **Completed**, and the requestor is told.
     - Treasury now uses RFP Approvals (`RoleName::rfpApprovalsUsers`).
     - **Later:** record the payment date and reference no. (check / transfer no.) on release.
2. Make the queue tabs in `ListRfpApprovals` follow the workflow's stages (they are still the six default tabs).
3. ✅ In-flight legacy imports get a route on import (`ApprovalEngine::adopt`, see [09](09-legacy-rfp-import.md)).
