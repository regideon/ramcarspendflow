<?php

namespace App\Enums;

/**
 * The action-button labels for the P2P invoice workflow, ported from the legacy
 * GoodReceiptActionLabel so the agreed terminology does not drift between roles.
 */
enum P2pInvoiceActionLabel: string
{
    /** Vendor sends a pending request to the AP processor. */
    case SubmitToAp = 'Submit to AP';

    /** Vendor re-sends a returned request. */
    case ResubmitToAp = 'Re-submit to AP';

    /** AP processor sends the submission to the supervisor. */
    case Submit = 'Submit';

    /** AP processor re-sends a supervisor-returned request. */
    case Resubmit = 'Re-submit';

    /** AP Supervisor approves and parks the invoice in SAP. */
    case Approve = 'Approve';

    /** AP Supervisor returns the request to the processor. */
    case ReturnToApProcessor = 'Return to AP Processor';

    /** AP processor / procurement returns a request to the vendor. */
    case ReturnToVendor = 'Return to Vendor';

    /** Raise an escalation. */
    case Escalate = 'Escalate';

    /** Dismiss the screen without changing the record. */
    case Cancel = 'Cancel';
}
