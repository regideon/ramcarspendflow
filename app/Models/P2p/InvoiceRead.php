<?php

namespace App\Models\P2p;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved AI read of one vendor invoice attachment, reused when the same file is read again.
 */
#[Table('p2p_invoice_reads')]
#[Fillable(['file_hash', 'reader_version', 'model', 'result', 'user_id'])]
class InvoiceRead extends Model
{
    /**
     * Who first read the document.
     *
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
            'reader_version' => 'integer',
            'result' => 'array',
        ];
    }
}
