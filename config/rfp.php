<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Auto-approve the approval matrix
    |--------------------------------------------------------------------------
    |
    | While matrix approval is parked (decided 2026-10-23), an RFP endorsed by
    | the AP Processor passes the matrix stage automatically and goes on to AP
    | processing. The matrix steps are still recorded on the RFP, marked as
    | auto-approved. Set RFP_AUTO_APPROVE_MATRIX=false when matrix approvers
    | start approving in the portal.
    |
    */

    'auto_approve_matrix' => (bool) env('RFP_AUTO_APPROVE_MATRIX', true),

    /*
    |--------------------------------------------------------------------------
    | Legacy RFP Portal import
    |--------------------------------------------------------------------------
    |
    | `rfp:import-legacy` and RFP Settings → Legacy Import REPLACE every RFP in
    | this database with the legacy data. Keep this on only until go-live, then
    | set RFP_LEGACY_IMPORT_ENABLED=false so nobody runs it by accident. Off by
    | default in production.
    |
    */

    'legacy_import_enabled' => (bool) env('RFP_LEGACY_IMPORT_ENABLED', env('APP_ENV') !== 'production'),
];
