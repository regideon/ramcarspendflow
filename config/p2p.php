<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Storage location (SLOC) classification
    |--------------------------------------------------------------------------
    |
    | SAP's material-document line (MSEG `LGORT`) is the source of truth for a
    | goods-receipt line's material classification. Add new SLOCs here; no code
    | change is required. Anything not listed — including a NULL SLOC — resolves
    | to `default_type`.
    |
    */

    'storage_location_types' => [
        'B001' => 'raw_material',
        'B002' => 'spare_part',
        'B602' => 'spare_part',
    ],

    'default_type' => 'unclassified',

    /*
    |--------------------------------------------------------------------------
    | SAP connection (P2P)
    |--------------------------------------------------------------------------
    |
    | Ported from the legacy P2P system (config/sap.php). Reads use OData GET;
    | the only write is the SOAP RFC that parks an invoice. Credentials live in
    | the environment only. TLS verification is ON by default (the legacy
    | system disabled it).
    |
    */

    'sap' => [
        'enabled' => env('SAP_ENABLED', false),
        'base_url' => env('SAP_BASE_URL', 'https://boomtube.motolite.com:8001'),
        'client' => env('SAP_CLIENT', '200'),
        'username' => env('SAP_USERNAME'),
        'password' => env('SAP_PASSWORD'),
        'incoming_invoice_endpoint' => env(
            'SAP_INCOMING_INVOICE_ENDPOINT',
            'https://boomtube.motolite.com:8001/sap/bc/srt/rfc/sap/zmm_ws_incominginvoice/400/zmm_ws_incominginvoice/zmm_ws_incominginvoice',
        ),
        'incoming_invoice_username' => env('SAP_INCOMING_INVOICE_USERNAME', env('SAP_USERNAME')),
        'incoming_invoice_password' => env('SAP_INCOMING_INVOICE_PASSWORD', env('SAP_PASSWORD')),
        'verify_tls' => env('SAP_VERIFY_TLS', true),
        'timeout' => (int) env('SAP_TIMEOUT', 60),
        'log_odata' => env('SAP_LOG_ODATA', true),
        'acdoca_poll_limit' => (int) env('SAP_ACDOCA_POLL_LIMIT', 50),
        // skip: leave POs for unknown vendors out; create: add an inactive stub vendor.
        'unknown_vendors' => env('SAP_UNKNOWN_VENDORS', 'skip'),
    ],
];
