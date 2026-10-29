import { test } from '@playwright/test';
import {
    advanceEscalation,
    expectEscalationHop,
    fixture,
    openAs,
    raiseVendorEscalation,
} from './helpers';

interface VendorEscalationCase {
    key: string;
    reason: string;
    firstHop: 'warehouse' | 'procurement';
    firstHopLabel: string;
    nextHopLabel: string;
}

/**
 * B. Vendor escalations: one case per vendor-selectable reason (there are 5).
 *
 * Routes seeded in `P2pDemoMasterDataSeeder`:
 *  - Quantity Difference / Incomplete GR: Warehouse -> Vendor
 *  - Wrong item description / Wrong unit of measure: Procurement -> Vendor
 *  - Other: Procurement -> Warehouse -> Vendor
 */
const vendorEscalations: VendorEscalationCase[] = [
    {
        key: 'B_qty',
        reason: 'Quantity Difference',
        firstHop: 'warehouse',
        firstHopLabel: 'Warehouse',
        nextHopLabel: 'Vendor',
    },
    {
        key: 'B_incomplete',
        reason: 'Incomplete GR',
        firstHop: 'warehouse',
        firstHopLabel: 'Warehouse',
        nextHopLabel: 'Vendor',
    },
    {
        key: 'B_item_desc',
        reason: 'Wrong item description',
        firstHop: 'procurement',
        firstHopLabel: 'Procurement',
        nextHopLabel: 'Vendor',
    },
    {
        key: 'B_uom',
        reason: 'Wrong unit of measure',
        firstHop: 'procurement',
        firstHopLabel: 'Procurement',
        nextHopLabel: 'Vendor',
    },
    {
        key: 'B_other',
        reason: 'Other',
        firstHop: 'procurement',
        firstHopLabel: 'Procurement',
        nextHopLabel: 'Warehouse',
    },
];

for (const scenario of vendorEscalations) {
    test(`vendor escalation - ${scenario.reason}`, async ({ browser }) => {
        const fx = fixture(scenario.key);

        const vendor = await openAs(browser, 'vendor');

        try {
            await raiseVendorEscalation(vendor.page, fx, scenario.reason);
        } finally {
            await vendor.context.close();
        }

        await expectEscalationHop(browser, scenario.firstHop, fx.gr, scenario.firstHopLabel);
        await advanceEscalation(browser, scenario.firstHop, fx.gr, scenario.nextHopLabel);
    });
}
