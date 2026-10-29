import { execFileSync } from 'node:child_process';

const BASE_URL = process.env.P2P_BASE_URL ?? 'http://ramcarspendflow.test';

/**
 * Refresh the deterministic E2E fixtures once per test run.
 *
 * The seeder only deletes records under the E2E PO/GR prefixes, so the rest of
 * the local database is left alone.
 */
export default async function globalSetup(): Promise<void> {
    const response = await fetch(`${BASE_URL}/hub/login`).catch((error: unknown) => {
        throw new Error(
            `Cannot reach ${BASE_URL}. Is the Herd site running? Original error: ${String(error)}`,
        );
    });

    if (!response.ok) {
        throw new Error(`Cannot reach ${BASE_URL}/hub/login (HTTP ${response.status}).`);
    }

    execFileSync(
        'php',
        ['artisan', 'db:seed', '--class=Database\\Seeders\\P2p\\P2pE2eSeeder', '--force', '--no-interaction'],
        { cwd: process.cwd(), stdio: 'inherit' },
    );
}
