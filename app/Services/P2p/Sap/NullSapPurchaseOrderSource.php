<?php

namespace App\Services\P2p\Sap;

use App\Contracts\P2p\SapPurchaseOrderSource;

/**
 * No-op SAP source used until the OData integration is wired up. The app runs on
 * seeded demo data (docs/p2p/07-sap-integration.md).
 */
class NullSapPurchaseOrderSource implements SapPurchaseOrderSource
{
    public function changedPurchaseOrders(array $filters = []): array
    {
        return [];
    }
}
