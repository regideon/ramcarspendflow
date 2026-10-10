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
];
