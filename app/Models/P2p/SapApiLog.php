<?php

namespace App\Models\P2p;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit log of SAP API calls (SOAP writes). Passwords are redacted before storage.
 */
#[Table('p2p_sap_api_logs')]
#[Fillable(['endpoint', 'request', 'response', 'http_status', 'execution_time', 'p2p_invoice_id'])]
class SapApiLog extends Model
{
    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'p2p_invoice_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'execution_time' => 'decimal:3',
        ];
    }
}
