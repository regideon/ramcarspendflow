<?php

namespace App\Models\Rfp;

use App\Enums\RfpStatus;
use App\Models\User;
use Database\Factories\Rfp\HistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only record of an RFP status change or action (e.g. "Approved"). Imported
 * legacy rows carry the legacy action and, where it is clear, the resulting status.
 */
#[Table('rfp_histories')]
#[Fillable(['rfp_id', 'user_id', 'action', 'from_status', 'to_status', 'remarks'])]
class History extends Model
{
    /** @use HasFactory<HistoryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Rfp, $this>
     */
    public function rfp(): BelongsTo
    {
        return $this->belongsTo(Rfp::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => RfpStatus::class,
            'to_status' => RfpStatus::class,
        ];
    }
}
