<?php

namespace App\Services\Comments;

use App\Models\Concerns\HasComments;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Posts a comment on a record and notifies the people @mentioned in it. Used by the
 * Add comment action and the chat bubble. Authors are never notified about their own comments,
 * and people who may not see internal comments (vendors) are never notified about one.
 */
class PostComment
{
    /**
     * @param  Model&HasComments  $record
     * @param  array{body: string, attachments?: list<string>|null, is_internal?: bool|null}  $data
     * @return Collection<int, User> the users who were notified
     */
    public function handle(Model $record, User $author, array $data): Collection
    {
        $internal = $record::supportsInternalComments()
            && $record::seesInternalComments($author)
            && (bool) ($data['is_internal'] ?? false);

        $comment = $record->comments()->create([
            'user_id' => $author->id,
            'body' => $data['body'],
            'attachments' => ($data['attachments'] ?? null) ?: null,
            'is_internal' => $internal,
        ]);

        $record->markCommentsReadBy($author);

        $mentioned = $record->mentionableUsers()
            ->whereKey($comment->mentionedUserIds())
            ->whereKeyNot($author->id)
            ->get()
            ->filter(fn (User $user): bool => ! $internal || $record::seesInternalComments($user))
            ->values();

        $mentioned->each(fn (User $user) => Notification::make()
            ->title("{$author->name} mentioned you on {$record->commentSubject()}")
            ->body(Str::limit($comment->plainText(), 120))
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->actions([
                Action::make('view')->label('Open')->url($record->commentUrlFor($user))->markAsRead(),
            ])
            ->sendToDatabase($user));

        return $mentioned;
    }

    /**
     * Private disk folder for a record type's comment attachments, e.g. "rfp-comments".
     */
    public static function attachmentDirectory(Model $record): string
    {
        return $record->getMorphClass().'-comments';
    }
}
