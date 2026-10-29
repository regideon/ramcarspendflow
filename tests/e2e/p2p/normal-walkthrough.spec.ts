import { expect, test } from '@playwright/test';
import {
    expectInvoiceStatus,
    fixture,
    goto,
    openApproval,
    openAs,
    processorSubmitToSupervisor,
    tableRow,
    tableSearch,
    vendorSubmit,
} from './helpers';

/**
 * A. Normal walkthrough: Vendor -> AP Processor -> AP Supervisor.
 *
 * The supervisor step stops at the review screen on purpose: clicking
 * "Approve" parks the invoice in SAP, which this suite never does.
 */
test.describe('A. Normal walkthrough (Vendor -> AP -> Supervisor)', () => {
    test('vendor submits, AP forwards, supervisor reviews without pushing to SAP', async ({ browser }) => {
        const fx = fixture('A');

        const vendor = await openAs(browser, 'vendor');

        try {
            await vendorSubmit(vendor.page, fx);
            await expectInvoiceStatus(vendor.page, fx.reference, 'Submitted to AP');
        } finally {
            await vendor.context.close();
        }

        const processor = await openAs(browser, 'processor');

        try {
            await openApproval(processor.page, fx.reference);
            await processorSubmitToSupervisor(processor.page);
            await expectInvoiceStatus(processor.page, fx.reference, 'Submitted to Checker');
        } finally {
            await processor.context.close();
        }

        const supervisor = await openAs(browser, 'supervisor');

        try {
            await openApproval(supervisor.page, fx.reference);

            // The supervisor can approve (SAP) or return; we only assert that
            // both are offered, and deliberately do NOT press Approve.
            await expect(supervisor.page.getByRole('button', { name: 'Approve', exact: true })).toBeVisible();
            await expect(
                supervisor.page.getByRole('button', { name: 'Return to AP Processor', exact: true }),
            ).toBeVisible();

            // The supervisor has no access to the P2P Requests list, so confirm
            // the stage on their own Approvals queue instead.
            await goto(supervisor.page, '/hub/p2p/approvals');
            await tableSearch(supervisor.page, fx.reference);
            await expect(tableRow(supervisor.page, fx.reference)).toContainText('Submitted to Checker');
        } finally {
            await supervisor.context.close();
        }
    });
});
