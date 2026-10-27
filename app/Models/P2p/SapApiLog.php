<?php

namespace App\Models\P2p;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit log of SAP communication (SOAP writes and OData reads). Passwords and
 * authorization headers are redacted before storage.
 */
#[Table('p2p_sap_api_logs')]
#[Fillable([
    'type', 'endpoint', 'request', 'response', 'http_status', 'error_message',
    'execution_time', 'user_id', 'p2p_invoice_id',
])]
class SapApiLog extends Model
{
    /** Outbound SOAP writes (e.g. parking an invoice). */
    public const string TYPE_SOAP = 'soap';

    /** Inbound OData reads performed during syncs and status polls. */
    public const string TYPE_ODATA = 'odata';

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'p2p_invoice_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<SapApiLog>  $query
     */
    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    /**
     * @param  Builder<SapApiLog>  $query
     */
    public function scopeFailed(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereNotNull('error_message')
                ->orWhere('http_status', '>=', 400);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'http_status' => 'integer',
            'execution_time' => 'decimal:3',
        ];
    }
}
