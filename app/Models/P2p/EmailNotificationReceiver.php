<?php

namespace App\Models\P2p;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Points of Contact — recipients of P2P overdue-warning email.
 */
#[Table('p2p_email_notification_receivers')]
#[Fillable(['email', 'name', 'notification_type', 'is_active'])]
class EmailNotificationReceiver extends Model
{
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
