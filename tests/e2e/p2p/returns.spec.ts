import { expect, test } from '@playwright/test';
import {
    activateAllTab,
    expectInvoiceStatus,
    fixture,
    goto,
    openApproval,
    openAs,
    processorSubmitToSupervisor,
    submitMountedAction,
    supervisorReturnToProcessor,
    tableRow,
    tableSearch,
    vendorSubmit,
} from './helpers';

/**
 * D. Return paths.
 *
 *  AP Supervisor -> "Return to AP Processor" (status 16)
 *  AP Processor -> "Re-submit" available, then "Return to Vendor" (status 15)
 *  Vendor       -> returned request opens for editing (Submit to AP / Cancel Request)
 */
test.describe('D. Return to AP and Return to Vendor', () => {
    test('supervisor returns to AP processor, then AP returns to vendor', async ({ browser }) => {
        const fx = fixture('D');

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
            await supervisorReturnToProcessor(supervisor.page, fx.reference);

            // The supervisor cannot open the P2P Requests list; check their own
            // Approvals queue for the returned status (status 16 is not on the
            // supervisor's "For my action" tab, so switch to All).
            await goto(supervisor.page, '/hub/p2p/approvals');
            await activateAllTab(supervisor.page);
            await tableSearch(supervisor.page, fx.reference);
            await expect(tableRow(supervisor.page, fx.reference)).toContainText('Returned to AP Processor');
        } finally {
            await supervisor.context.close();
        }

        const processorAgain = await openAs(browser, 'processor');

        try {
            // A returned-to-AP request offers Re-submit, not Submit.
            await openApproval(processorAgain.page, fx.reference);
            await expect(
                processorAgain.page.getByRole('button', { name: 'Re-submit', exact: true }),
            ).toBeVisible();
            await expect(
                processorAgain.page.getByRole('button', { name: 'Return to Vendor', exact: true }),
            ).toBeVisible();

            await processorAgain.page.getByRole('button', { name: 'Return to Vendor', exact: true }).click();
            await processorAgain.page
                .locator('[id="mountedActionSchema0.remarks_ap"]')
                .fill('E2E: returned to the vendor from the AP processor.');
            await submitMountedAction(processorAgain.page, 'Submit');
            await processorAgain.page.waitForTimeout(3_500);

            await expectInvoiceStatus(processorAgain.page, fx.reference, 'Returned to Vendor');
        } finally {
            await processorAgain.context.close();
        }

        const vendorAgain = await openAs(browser, 'vendor');

        try {
            await goto(vendorAgain.page, '/hub/p2p/invoices');
            await activateAllTab(vendorAgain.page);
            await tableSearch(vendorAgain.page, fx.reference);

            const row = tableRow(vendorAgain.page, fx.reference);
            await expect(row).toBeVisible();
            await expect(row).toContainText('Returned to Vendor');

            await row.getByRole('link', { name: 'Resubmit' }).click();
            await vendorAgain.page.waitForURL(/\/hub\/p2p\/bulk-goods-receipt/, { timeout: 60_000 });

            // Returned requests reopen in editing mode.
            await expect(
                vendorAgain.page.getByRole('button', { name: 'Submit to AP', exact: true }),
            ).toBeVisible();
            await expect(
                vendorAgain.page.getByRole('button', { name: 'Cancel Request', exact: true }),
            ).toBeVisible();
        } finally {
            await vendorAgain.context.close();
        }
    });
});
