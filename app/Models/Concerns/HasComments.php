<?php

namespace App\Models\Concerns;

use App\Models\Comment;
use App\Models\CommentRead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/**
 * A record people can comment on (RFP, P2P invoice). Comments are deleted with the record,
 * since a polymorphic relation has no foreign key to cascade.
 */
trait HasComments
{
    public static function bootHasComments(): void
    {
        static::deleted(function (self $record): void {
            if (! method_exists($record, 'isForceDeleting') || $record->isForceDeleting()) {
                $record->comments()->delete();
                $record->commentReads()->delete();
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
     * @return MorphMany<CommentRead, $this>
     */
    public function commentReads(): MorphMany
    {
        return $this->morphMany(CommentRead::class, 'commentable');
    }

    /**
     * The comments the user may read: internal ones are hidden from people who may not see them.
     *
     * @return MorphMany<Comment, $this>
     */
    public function commentsVisibleTo(User $user): MorphMany
    {
        return static::constrainCommentsFor($this->comments(), $user);
    }

    /**
     * @template TQuery of Builder|MorphMany
     *
     * @param  TQuery  $comments
     * @return TQuery
     */
    public static function constrainCommentsFor(Builder|MorphMany $comments, User $user): Builder|MorphMany
    {
        return static::seesInternalComments($user) ? $comments : $comments->where('comments.is_internal', false);
    }

    /**
     * Whether staff can mark a comment as internal on this kind of record (only where vendors take part).
     */
    public static function supportsInternalComments(): bool
    {
        return false;
    }

    public static function seesInternalComments(User $user): bool
    {
        return true;
    }

    /**
     * Records the user may open, and so read and post comments on.
     *
     * @param  Builder<static>  $query
     */
    abstract public function scopeCommentableBy(Builder $query, User $user): void;

    /**
     * Users who can open this record and so may be @mentioned on it.
     *
     * @return Builder<User>
     */
    abstract public function mentionableUsers(): Builder;

    /**
     * How the record is named in notifications and the inbox, e.g. the RFP number.
     */
    abstract public function commentSubject(): string;

    /**
     * Where the given user opens this record.
     */
    abstract public function commentUrlFor(User $user): string;

    /**
     * The kind of record, shown in the chat (e.g. "RFP", "P2P invoice").
     */
    public static function commentTypeLabel(): string
    {
        return Str::headline(class_basename(static::class));
    }

    public function canBeCommentedOnBy(User $user): bool
    {
        return static::query()->commentableBy($user)->whereKey($this->getKey())->exists();
    }

    /**
     * Adds `unread_comments_count` for the user to every row in one query, so a list
     * does not look up each row's unread messages separately.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWithUnreadCommentsCountFor(Builder $query, User $user): void
    {
        $query->withCount(['comments as unread_comments_count' => fn (Builder $comments) => static::constrainCommentsFor($comments->reorder(), $user)
            ->where('comments.user_id', '!=', $user->id)
            ->whereRaw('comments.id > coalesce((select comment_reads.last_read_comment_id from comment_reads where comment_reads.commentable_type = comments.commentable_type and comment_reads.commentable_id = comments.commentable_id and comment_reads.user_id = ?), 0)', [$user->id])]);
    }

    /**
     * Comments by other people that the user has not seen yet.
     */
    public function unreadCommentsCountFor(User $user): int
    {
        // Already counted by withUnreadCommentsCountFor() for the signed-in user's list
        if (array_key_exists('unread_comments_count', $this->getAttributes()) && $user->is(auth()->user())) {
            return (int) $this->getAttribute('unread_comments_count');
        }

        $lastRead = $this->commentReads()->where('user_id', $user->id)->value('last_read_comment_id') ?? 0;

        return $this->commentsVisibleTo($user)->whereNot('user_id', $user->id)->where('id', '>', $lastRead)->count();
    }

    public function markCommentsReadBy(User $user): void
    {
        $latest = $this->comments()->reorder()->max('id');

        if ($latest) {
            $this->commentReads()->updateOrCreate(['user_id' => $user->id], ['last_read_comment_id' => $latest]);
        }
    }
}
