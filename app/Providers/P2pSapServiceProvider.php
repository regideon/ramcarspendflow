<?php

namespace App\Providers;

use App\Contracts\P2p\SapPurchaseOrderSource;
use App\Services\P2p\Sap\NullSapPurchaseOrderSource;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the P2P SAP integration points. Swap the Null implementations for real
 * SAP services when the integration lands (docs/p2p/07-sap-integration.md).
 */
class P2pSapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SapPurchaseOrderSource::class, NullSapPurchaseOrderSource::class);
    }
}
