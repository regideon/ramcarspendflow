<?php

namespace App\Models\Concerns;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A record people can comment on (RFP, later P2P invoices). Comments are deleted with the record,
 * since a polymorphic relation has no foreign key to cascade.
 */
trait HasComments
{
    public static function bootHasComments(): void
    {
        static::deleted(function (self $record): void {
            if (! method_exists($record, 'isForceDeleting') || $record->isForceDeleting()) {
                $record->comments()->delete();
            }
        });
    }

    /**
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->oldest('id');
    }

    /**
     * Users who can open this record and so may be @mentioned on it.
     *
     * @return Builder<User>
     */
    abstract public function mentionableUsers(): Builder;
}
