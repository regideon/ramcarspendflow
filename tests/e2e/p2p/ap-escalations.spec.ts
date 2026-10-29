import { test } from '@playwright/test';
import {
    advanceEscalation,
    expectEscalationHop,
    expectInvoiceStatus,
    fixture,
    openAs,
    raiseProcessorEscalation,
    vendorSubmit,
} from './helpers';

/**
 * C. AP escalations: the two reasons an AP Processor can raise.
 *
 * Each case starts with the vendor submitting the fixture GR, then the AP
 * Processor raises the escalation from the submission screen.
 *
 *  - Price difference: Procurement -> Warehouse (Reverse & re-GR) -> Procurement -> Vendor.
 *    The suite stops at the Procurement hop on purpose; the Warehouse step action
 *    reverses a goods receipt, which must not run in an automated pass.
 *  - Other: Procurement -> Warehouse -> origin. Both hops are walked. The seeded
 *    "Other" reason stores its configured origin ("any") on the terminal hop, so
 *    that is what the last assertion expects.
 */
test.describe('C. AP escalations', () => {
    test('price difference is routed to Procurement', async ({ browser }) => {
        const fx = fixture('C_price');

        const vendor = await openAs(browser, 'vendor');

        try {
            await vendorSubmit(vendor.page, fx);
            await expectInvoiceStatus(vendor.page, fx.reference, 'Submitted to AP');
        } finally {
            await vendor.context.close();
        }

        const processor = await openAs(browser, 'processor');

        try {
            await raiseProcessorEscalation(processor.page, fx.reference, 'Price difference');
            await expectInvoiceStatus(processor.page, fx.reference, 'Escalate to Procurement');
        } finally {
            await processor.context.close();
        }

        await expectEscalationHop(browser, 'procurement', fx.gr, 'Procurement');
    });

    test('other walks Procurement -> Warehouse -> AP Processor', async ({ browser }) => {
        const fx = fixture('C_other');

        const vendor = await openAs(browser, 'vendor');

        try {
            await vendorSubmit(vendor.page, fx);
            await expectInvoiceStatus(vendor.page, fx.reference, 'Submitted to AP');
        } finally {
            await vendor.context.close();
        }

        const processor = await openAs(browser, 'processor');

        try {
            await raiseProcessorEscalation(processor.page, fx.reference, 'Other');
            await expectInvoiceStatus(processor.page, fx.reference, 'Escalate to Procurement');
        } finally {
            await processor.context.close();
        }

        await expectEscalationHop(browser, 'procurement', fx.gr, 'Procurement');
        await advanceEscalation(browser, 'procurement', fx.gr, 'Warehouse');
        // The reason is configured with origin "any", so the terminal hop's
        // current role is "any" rather than "AP Processor".
        await advanceEscalation(browser, 'warehouse', fx.gr, 'any');
    });
});
