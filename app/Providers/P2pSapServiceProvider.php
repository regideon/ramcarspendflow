<?php

namespace App\Providers;

use App\Contracts\P2p\SapInvoiceParkingGateway;
use App\Contracts\P2p\SapPurchaseOrderSource;
use App\Services\P2p\Sap\NullSapInvoiceParkingGateway;
use App\Services\P2p\Sap\NullSapPurchaseOrderSource;
use App\Services\P2p\Sap\ODataSapPurchaseOrderSource;
use App\Services\P2p\Sap\P2pSoapInvoiceGateway;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the P2P SAP integration points.
 *
 * With SAP_ENABLED=false the null implementations keep the demo/offline flows
 * working without any network call. Flip the flag when SAP access is available.
 */
class P2pSapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SapPurchaseOrderSource::class, function ($app): SapPurchaseOrderSource {
            return config('p2p.sap.enabled')
                ? $app->make(ODataSapPurchaseOrderSource::class)
                : $app->make(NullSapPurchaseOrderSource::class);
        });

        $this->app->bind(SapInvoiceParkingGateway::class, function ($app): SapInvoiceParkingGateway {
            return config('p2p.sap.enabled')
                ? $app->make(P2pSoapInvoiceGateway::class)
                : $app->make(NullSapInvoiceParkingGateway::class);
        });
    }
}
