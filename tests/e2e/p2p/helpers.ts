import { expect, type Browser, type BrowserContext, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
import path from 'node:path';

export const BASE_URL = process.env.P2P_BASE_URL ?? 'http://ramcarspendflow.test';

export const MANIFEST_PATH = path.resolve(process.cwd(), 'storage/app/p2p-e2e-fixtures.json');

export const PDF_PATH = path.resolve(process.cwd(), 'tests/e2e/p2p/fixtures/sample-invoice.pdf');

export interface Fixture {
    po: string;
    gr: string;
    state_id: number;
    reference: string;
}

export type Role = 'vendor' | 'processor' | 'supervisor' | 'procurement' | 'warehouse';

export interface Manifest {
    password: string;
    users: Record<Role, string>;
    tax_code: string;
    fixtures: Record<string, Fixture>;
}

let cachedManifest: Manifest | null = null;

/**
 * The manifest written by `P2pE2eSeeder` during global setup.
 */
export function manifest(): Manifest {
    cachedManifest ??= JSON.parse(readFileSync(MANIFEST_PATH, 'utf8')) as Manifest;

    return cachedManifest;
}

export function fixture(key: string): Fixture {
    const value = manifest().fixtures[key];

    if (value === undefined) {
        throw new Error(`No E2E fixture "${key}". Run: php artisan db:seed --class=Database\\Seeders\\P2p\\P2pE2eSeeder`);
    }

    return value;
}

/**
 * Open an isolated browser context and sign in as one of the demo roles.
 * Every context is independent, so a test can act as several roles in turn.
 */
export async function openAs(
    browser: Browser,
    role: Role,
): Promise<{ context: BrowserContext; page: Page }> {
    const context = await browser.newContext({
        baseURL: BASE_URL,
        ignoreHTTPSErrors: true,
        viewport: { width: 1500, height: 1000 },
    });

    const page = await context.newPage();
    page.setDefaultTimeout(30_000);
    page.setDefaultNavigationTimeout(60_000);

    // Laravel Query Detector raises a native alert in local, and Livewire asks
    // to refresh a stale page with a confirm; accepting both keeps the UI usable.
    page.on('dialog', (dialog) => {
        void dialog.accept().catch(() => undefined);
    });

    await login(page, manifest().users[role], manifest().password);

    return { context, page };
}

/**
 * Navigate defensively. Filament's SPA/Livewire boot can abort a navigation
 * issued while the previous page is still settling, so retry.
 */
export async function goto(page: Page, url: string): Promise<void> {
    const targetPath = new URL(url, BASE_URL).pathname;

    for (let attempt = 1; attempt <= 3; attempt += 1) {
        try {
            await page.goto(url, { waitUntil: 'domcontentloaded' });
            await page.waitForLoadState('load').catch(() => undefined);

            return;
        } catch (error) {
            // The app sometimes redirects while we navigate; if it landed on the
            // intended page anyway, the navigation succeeded.
            if (new URL(page.url()).pathname === targetPath) {
                await page.waitForTimeout(800);

                return;
            }

            if (attempt === 3) {
                throw error;
            }

            await page.waitForTimeout(1_500);
        }
    }
}

export async function login(page: Page, email: string, password: string): Promise<void> {
    await page.goto('/hub/login');
    await page.getByRole('textbox', { name: 'Email address*' }).fill(email);
    await page.getByRole('textbox', { name: 'Password*' }).fill(password);
    await page.getByRole('button', { name: 'Sign in' }).click();

    // `/hub/login` itself matches a naive `/hub` prefix check, which used to
    // return before the session was established. Wait for a real panel page.
    await page.waitForURL(
        (url) => url.pathname.startsWith('/hub')
            && !url.pathname.startsWith('/hub/login')
            && !url.pathname.startsWith('/hub/password-reset'),
        { timeout: 60_000 },
    );

    await page.waitForLoadState('load').catch(() => undefined);
    // Let the dashboard's widgets finish their first Livewire roundtrips.
    await page.waitForTimeout(2_500);
}

/**
 * Narrow a Filament table with its own search box (not the global search).
 */
export async function tableSearch(page: Page, text: string): Promise<void> {
    let search = page.locator('.fi-ta input[type="search"]').first();

    // A Livewire roundtrip can leave the page blank after a redirect; reload
    // once before failing.
    if (!(await search.isVisible().catch(() => false))) {
        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(2_500);
        search = page.locator('.fi-ta input[type="search"]').first();
    }

    await expect(search).toBeVisible();
    await search.fill(text);
    await page.waitForTimeout(1_200);
}

export function tableRow(page: Page, text: string) {
    return page.locator('.fi-ta table tbody tr', { hasText: text }).first();
}

/**
 * Some lists open on "For my action" / "In Progress"; switch to "All" so a
 * returned/resolved record is still visible.
 */
export async function activateAllTab(page: Page): Promise<void> {
    const tab = page.getByRole('tab', { name: /^All\b/ });

    if ((await tab.count()) > 0) {
        await tab.first().click();
        await page.waitForTimeout(1_000);
    }
}

/**
 * Vendor: Purchase Orders -> Process GRs -> Goods Receipts -> Process Selected
 * -> the bulk goods-receipt form for the fixture GR.
 */
export async function openVendorGr(page: Page, fx: Fixture): Promise<void> {
    await goto(page, '/hub/p2p/purchase-orders');
    await tableSearch(page, fx.po);

    const poRow = tableRow(page, fx.po);
    await expect(poRow).toBeVisible();
    await poRow.locator('.fi-ta-record-checkbox').first().check();
    // The vendor/company row lock pushes table filters after a selection.
    await page.waitForTimeout(1_000);

    await page.getByRole('button', { name: 'Process GRs' }).click();
    await page.getByRole('button', { name: 'Yes, proceed.' }).click();
    await page.waitForURL(/\/hub\/p2p\/goods-receipts/, { timeout: 60_000 });
    await page.waitForTimeout(1_000);

    const grRow = tableRow(page, fx.gr);
    await expect(grRow).toBeVisible();
    await grRow.locator('.fi-ta-record-checkbox').first().check();
    await page.getByRole('button', { name: 'Process Selected' }).click();
    await page.waitForURL(/\/hub\/p2p\/bulk-goods-receipt/, { timeout: 60_000 });

    await expect(page.locator('[id="content.reference_number"]')).toBeVisible();
}

/**
 * Tick every GR line and give it a quantity. Ticking alone does not reliably
 * fill Inv Qty through Livewire, so the quantity is set explicitly.
 */
export async function tickAllLines(page: Page): Promise<void> {
    for (let attempt = 1; attempt <= 3; attempt += 1) {
        const boxes = page.locator('input[type="checkbox"][id*="goodReceiptItems"][id$=".include"]');
        await expect(boxes.first()).toBeVisible();

        for (let index = 0; index < (await boxes.count()); index += 1) {
            const box = boxes.nth(index);

            if (!(await box.isChecked())) {
                await box.check();
            }
        }

        // Let the afterStateUpdated roundtrips settle before pricing the lines.
        await page.waitForTimeout(1_200);

        const quantities = page.locator('input[type="number"][id*="goodReceiptItems"]');

        for (let index = 0; index < (await quantities.count()); index += 1) {
            const input = quantities.nth(index);
            const value = await input.inputValue();

            if (value !== '' && value !== '0') {
                continue;
            }

            const id = await input.getAttribute('id');
            const remainingSelector = `input[id="${(id ?? '').replace(/\.quantity$/, '.quantity_remaining')}"]`;
            const remaining = await page.locator(remainingSelector).inputValue().catch(() => '');

            await input.fill(remaining || '1');
            await input.blur();
        }

        await page.waitForTimeout(1_500);

        const totalText = await page.locator('[id="content.overall_invoice_total"]').inputValue();
        const total = Number(totalText.replace(/[^0-9.]/g, ''));

        if (total > 0) {
            return;
        }

        // A late Livewire render can reset the inputs; run the whole pass again.
        await page.waitForTimeout(1_000);
    }

    const totalText = await page.locator('[id="content.overall_invoice_total"]').inputValue();

    throw new Error(`Vendor form total is "${totalText}" — the lines were not selected/priced.`);
}

export async function fillReference(page: Page, reference: string): Promise<void> {
    const input = page.locator('[id="content.reference_number"]');
    await input.click();
    await input.fill(reference);
    await input.press('Enter');
    await page.waitForTimeout(1_200);

    await expect(page.getByText(reference, { exact: true }).first()).toBeVisible();
}

export async function uploadInvoice(page: Page): Promise<void> {
    await page.locator('input[type="file"]').first().setInputFiles(PDF_PATH);
    await expect(page.getByText(path.basename(PDF_PATH)).first()).toBeVisible({ timeout: 60_000 });
    // The filename appears while the temp upload is still in flight; wait for
    // FilePond to confirm it before submitting.
    await expect(page.getByText(/Upload complete/i).first()).toBeVisible({ timeout: 60_000 });
    await page.waitForTimeout(2_000);
}

/**
 * Submit the open vendor form to AP. When the lines are not 3-way matched the
 * page asks for confirmation first ("Submit anyway").
 */
export async function submitVendorForm(page: Page): Promise<void> {
    await page.getByRole('button', { name: /^Submit to AP/ }).click();

    const submitAnyway = page.getByRole('button', { name: 'Submit anyway' });

    // The confirmation modal ("Submit anyway") only appears for flagged lines
    // and renders a moment after the click; wait for it instead of probing.
    const modalAppeared = submitAnyway
        .waitFor({ state: 'visible', timeout: 6_000 })
        .then(() => 'modal' as const)
        .catch(() => 'none' as const);

    const redirected = page
        .waitForURL(/\/hub\/p2p\/(invoices|goods-receipts)/, { timeout: 6_000 })
        .then(() => 'redirect' as const)
        .catch(() => 'none' as const);

    if ((await Promise.race([modalAppeared, redirected])) === 'modal') {
        await submitAnyway.click();
    } else {
        const body = (await page.innerText('body')).replace(/\s+/g, ' ');
        const toast = body.includes('Could not submit') || body.includes('Please') ? body.slice(0, 800) : '';

        throw new Error(
            'Vendor submit neither opened the confirmation modal nor redirected — it was rejected by validation.'
            + (toast ? ` Page said: ${toast}` : ''),
        );
    }

    await page.waitForTimeout(3_500);
}

export async function vendorSubmit(page: Page, fx: Fixture): Promise<void> {
    await openVendorGr(page, fx);
    await tickAllLines(page);
    await fillReference(page, fx.reference);
    await uploadInvoice(page);
    await submitVendorForm(page);
}

/**
 * Assert the invoice reached a status on the P2P Requests list.
 */
export async function expectInvoiceStatus(page: Page, reference: string, status: string): Promise<void> {
    await goto(page, '/hub/p2p/invoices');
    await activateAllTab(page);
    await tableSearch(page, reference);

    const row = tableRow(page, reference);
    await expect(row).toBeVisible();
    await expect(row).toContainText(status);
}

/**
 * Open a submission from the AP Approvals queue (processor or supervisor).
 */
export async function openApproval(page: Page, reference: string): Promise<void> {
    await goto(page, '/hub/p2p/approvals');
    await activateAllTab(page);
    await tableSearch(page, reference);

    const row = tableRow(page, reference);
    await expect(row).toBeVisible();

    const reviewLink = row.getByRole('link', { name: 'Review' });

    if ((await reviewLink.count()) > 0) {
        await reviewLink.first().click();
    } else {
        await row.getByRole('button', { name: 'Review' }).first().click();
    }

    await page.waitForURL(/\/hub\/p2p\/bulk-accounts-payable/, { timeout: 60_000 });
    await expect(page.locator('[id="form.tax_code"]')).toBeVisible();
}

/**
 * Pick the Tax Code. Filament renders the select button with the field id, and
 * the label loses its required asterisk for the supervisor.
 */
export async function selectTaxCode(page: Page, taxCode: string): Promise<void> {
    await page.locator('[id="form.tax_code"]').click();
    await page.waitForTimeout(600);
    await page.keyboard.type(taxCode);
    await page.waitForTimeout(1_000);
    await page.getByRole('option').filter({ hasText: taxCode }).first().click();
}

/**
 * Pick an option in a Filament searchable Select by its field label.
 */
export async function selectOptionByLabel(
    page: Page,
    labelPattern: string | RegExp,
    optionText: string,
): Promise<void> {
    const field = page
        .locator('.fi-fo-field')
        .filter({ has: page.locator('label.fi-fo-field-label', { hasText: labelPattern }) })
        .last();

    await field.locator('.fi-select-input-btn').click();
    await page.waitForTimeout(600);
    await page.keyboard.type(optionText);
    await page.waitForTimeout(1_000);
    await page.getByRole('option').filter({ hasText: optionText }).first().click();
}

/**
 * AP Processor: set the Tax Code and forward the submission to the supervisor.
 */
export async function processorSubmitToSupervisor(page: Page, taxCode = manifest().tax_code): Promise<void> {
    await selectTaxCode(page, taxCode);
    await page.getByRole('button', { name: 'Submit', exact: true }).click();
    await page.waitForTimeout(3_500);
}

export async function supervisorReturnToProcessor(page: Page, reference: string): Promise<void> {
    await openApproval(page, reference);

    // The supervisor must never push the invoice to SAP in these tests.
    await expect(page.getByRole('button', { name: 'Approve', exact: true })).toBeVisible();

    await page.getByRole('button', { name: 'Return to AP Processor', exact: true }).click();
    await page.locator('[id="mountedActionSchema0.remarks_checker"]').fill('E2E: returned to the AP processor.');
    await submitMountedAction(page, 'Submit');
    await page.waitForTimeout(3_500);
}

export async function processorReturnToVendor(page: Page, reference: string): Promise<void> {
    await openApproval(page, reference);

    await page.getByRole('button', { name: 'Return to Vendor', exact: true }).click();
    await page.locator('[id="mountedActionSchema0.remarks_ap"]').fill('E2E: returned to the vendor.');
    await submitMountedAction(page, 'Submit');
    await page.waitForTimeout(3_500);
}

/**
 * Click a Filament action modal's submit button. The footer submit label is
 * action-specific (the processor escalation is "Submit", the vendor escalation
 * is "Escalate"), and the page-level button with the same text sits behind the
 * modal overlay.
 */
export async function submitMountedAction(page: Page, label: string): Promise<void> {
    const footer = page.locator('.fi-modal-footer').last();
    await expect(footer).toBeVisible();
    await footer.getByRole('button', { name: label, exact: true }).click();
}

/**
 * Vendor: raise an escalation for the fixture GR from the bulk goods-receipt
 * form (all lines ticked, quantity filled).
 */
export async function raiseVendorEscalation(page: Page, fx: Fixture, reason: string): Promise<void> {
    await openVendorGr(page, fx);
    await tickAllLines(page);

    await page.getByRole('button', { name: 'Escalate', exact: true }).first().click();
    await selectOptionByLabel(page, 'Reason', reason);
    await page.locator('[id="mountedActionSchema0.escalationRemarks"]').fill(`E2E ${reason} escalation.`);
    await submitMountedAction(page, 'Escalate');
    await page.waitForTimeout(3_500);
}

/**
 * AP Processor: escalate the submission with the given reason.
 *
 * The per-line "Report" tick is currently disabled in the page schema, so the
 * escalation covers the whole request (the page falls back to every line when
 * none are ticked).
 */
export async function raiseProcessorEscalation(page: Page, reference: string, reason: string): Promise<void> {
    await openApproval(page, reference);

    await page.getByRole('button', { name: 'Escalate', exact: true }).first().click();
    await selectOptionByLabel(page, 'Reason', reason);
    await page.locator('[id="mountedActionSchema0.escalationRemarks"]').fill(`E2E ${reason} escalation.`);
    await submitMountedAction(page, 'Submit');
    await page.waitForTimeout(3_500);
}

/**
 * Assert which team currently owns an escalation.
 */
export async function expectEscalationHop(
    browser: Browser,
    role: Role,
    grNumber: string,
    hop: string,
): Promise<void> {
    const { context, page } = await openAs(browser, role);

    try {
        await goto(page, '/hub/p2p/escalations');
        await activateAllTab(page);
        await tableSearch(page, grNumber);

        const row = tableRow(page, grNumber);
        await expect(row).toBeVisible();
        await expect(row).toContainText(hop);
    } finally {
        await context.close();
    }
}

/**
 * Advance an escalation one hop from the queue it is waiting on, then assert
 * where it landed next. Generic "Advance" only — never the SAP Reverse & re-GR
 * step action.
 */
export async function advanceEscalation(
    browser: Browser,
    role: Role,
    grNumber: string,
    nextHop: string,
): Promise<void> {
    const { context, page } = await openAs(browser, role);

    try {
        await goto(page, '/hub/p2p/escalations');
        await activateAllTab(page);
        await tableSearch(page, grNumber);

        const row = tableRow(page, grNumber);
        await expect(row).toBeVisible();

        const viewLink = row.getByRole('link', { name: 'View' });

        if ((await viewLink.count()) > 0) {
            await viewLink.first().click();
        } else {
            await row.getByRole('button', { name: 'View' }).first().click();
        }

        await page.waitForURL(/\/hub\/p2p\/bulk-goods-receipt/, { timeout: 60_000 });
        // Let the page's Livewire/Alpine state settle before opening the action.
        await page.waitForTimeout(2_000);
        await page.getByRole('button', { name: 'Advance', exact: true }).click();
        await page.locator('[id="mountedActionSchema0.remarks"]').fill(`E2E: advanced ${grNumber}.`);
        await page.waitForTimeout(600);
        await submitMountedAction(page, 'Advance');
        await page.waitForTimeout(2_500);

        if (await page.locator('[id="mountedActionSchema0.remarks"]').isVisible().catch(() => false)) {
            const body = (await page.innerText('body')).replace(/\s+/g, ' ');

            throw new Error(`Advance modal stayed open after submit. Page said: ${body.slice(0, 1_200)}`);
        }
    } finally {
        // Close while the action's redirect is still settling: navigating this
        // page after a Livewire redirect races with it (net::ERR_ABORTED).
        await context.close();
    }

    // Re-open fresh to read the persisted hop.
    await expectEscalationHop(browser, role, grNumber, nextHop);
}
