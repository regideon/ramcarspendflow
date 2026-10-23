<?php

namespace App\Models\Rfp;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * One run of the legacy RFP Portal import started from the Settings page.
 */
#[Table('legacy_import_runs')]
#[Fillable(['started_by', 'status', 'output', 'finished_at'])]
class LegacyImportRun extends Model
{
    public const string RUNNING = 'running';

    public const string SUCCEEDED = 'succeeded';

    public const string FAILED = 'failed';

    /**
     * Whether an import is in progress (one started in the last two hours that has not finished).
     */
    public static function isRunning(): bool
    {
        return static::query()
            ->where('status', self::RUNNING)
            ->where('created_at', '>=', now()->subHours(2))
            ->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'finished_at' => 'datetime',
        ];
    }
}
