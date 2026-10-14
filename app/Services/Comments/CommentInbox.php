<?php

namespace App\Services\Comments;

use App\Models\Comment;
use App\Models\Concerns\HasComments;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * The user's conversations across every commentable record type (RFP, P2P invoice):
 * the threads they can open that have messages they can see, newest first, with unread counts.
 */
class CommentInbox
{
    /**
     * Commentable record types, from the morph map.
     *
     * @return list<class-string<Model>>
     */
    public static function commentableClasses(): array
    {
        return collect(Relation::morphMap())
            ->filter(fn (string $class): bool => in_array(HasComments::class, class_uses_recursive($class)))
            ->values()
            ->all();
    }

    /**
     * @return class-string<Model>|null
     */
    public static function classFor(string $type): ?string
    {
        $class = Relation::getMorphedModel($type);

        return in_array($class, static::commentableClasses(), true) ? $class : null;
    }

    /**
     * Each thread is the record with `latest_comment_id`, `unread_comments_count` and its `latestComment`.
     *
     * @return Collection<int, Model>
     */
    public function threads(User $user, int $limit = 30): Collection
    {
        $threads = collect(static::commentableClasses())
            ->flatMap(fn (string $class) => $class::query()
                ->commentableBy($user)
                ->whereHas('comments', fn (Builder $comments) => $class::constrainCommentsFor($comments, $user))
                ->withMax(['comments as latest_comment_id' => fn (Builder $comments) => $class::constrainCommentsFor($comments->reorder(), $user)], 'id')
                ->withCount(['comments as unread_comments_count' => fn (Builder $comments) => $this->unread($class::constrainCommentsFor($comments->reorder(), $user), $user)])
                ->orderByDesc('latest_comment_id')
                ->limit($limit)
                ->get())
            ->sortByDesc('latest_comment_id')
            ->take($limit)
            ->values();

        $latest = Comment::query()->with('user')->findMany($threads->pluck('latest_comment_id'))->keyBy('id');

        return $threads->each(fn (Model $thread) => $thread->setRelation('latestComment', $latest->get($thread->latest_comment_id)));
    }

    /**
     * Unread messages across all the user's threads, for the bubble badge.
     */
    public function unreadTotal(User $user): int
    {
        return collect(static::commentableClasses())->sum(fn (string $class): int => $this->unread(
            $class::constrainCommentsFor(Comment::query(), $user)
                ->where('commentable_type', (new $class)->getMorphClass())
                ->whereIn('commentable_id', $class::query()->commentableBy($user)->select((new $class)->getQualifiedKeyName())),
            $user,
        )->count());
    }

    /**
     * Comments by others newer than the user's last read comment on the same record.
     *
     * @template TQuery of Builder|MorphMany
     *
     * @param  TQuery  $comments
     * @return TQuery
     */
    protected function unread(Builder|MorphMany $comments, User $user): Builder|MorphMany
    {
        return $comments
            ->whereNot('comments.user_id', $user->id)
            ->where('comments.id', '>', fn (QueryBuilder $read) => $read
                ->from('comment_reads')
                ->selectRaw('coalesce(max(last_read_comment_id), 0)')
                ->where('comment_reads.user_id', $user->id)
                ->whereColumn('comment_reads.commentable_type', 'comments.commentable_type')
                ->whereColumn('comment_reads.commentable_id', 'comments.commentable_id'));
    }
}
