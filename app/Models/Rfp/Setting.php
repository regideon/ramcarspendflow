<?php

namespace App\Models\Rfp;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * RFP-wide settings, edited by Admins in RFP Settings. There is only one row: use current().
 */
#[Table('rfp_settings')]
#[Fillable(['bulk_approval_item_limit', 'bulk_approval_total_limit'])]
class Setting extends Model
{
    public const float DEFAULT_BULK_APPROVAL_ITEM_LIMIT = 200_000;

    public const float DEFAULT_BULK_APPROVAL_TOTAL_LIMIT = 500_000;

    public static function current(): self
    {
        return static::query()->oldest('id')->firstOrCreate([], [
            'bulk_approval_item_limit' => self::DEFAULT_BULK_APPROVAL_ITEM_LIMIT,
            'bulk_approval_total_limit' => self::DEFAULT_BULK_APPROVAL_TOTAL_LIMIT,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bulk_approval_item_limit' => 'float',
            'bulk_approval_total_limit' => 'float',
        ];
    }
}
