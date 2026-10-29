<?php

namespace App\Models\P2p;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * One SAP PO/GR sync run. Keeps the counts that explain why a run synced
 * nothing: discovered POs, skipped unknown vendors/companies, errors.
 */
#[Table('p2p_sap_sync_runs')]
#[Fillable([
    'vendor_code', 'window_from', 'window_to', 'filters', 'status',
    'discovered', 'synced', 'skipped_unknown_vendor', 'skipped_unknown_company',
    'created_vendors', 'reversals', 'errors', 'error_message', 'started_at', 'finished_at',
])]
class SapSyncRun extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'window_from' => 'date',
            'window_to' => 'date',
            'discovered' => 'integer',
            'synced' => 'integer',
            'skipped_unknown_vendor' => 'integer',
            'skipped_unknown_company' => 'integer',
            'created_vendors' => 'integer',
            'reversals' => 'integer',
            'errors' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
