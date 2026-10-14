<?php

namespace App\Filament\Actions;

use App\Models\Concerns\HasComments;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Js;

/**
 * A table row's chat icon: shows the row's unread messages and opens its thread in the chat bubble.
 */
class OpenCommentsAction
{
    /**
     * @param  (Closure(Model): ?Model)|null  $thread  the commented record for a row, e.g. an escalation's invoice
     */
    public static function make(?Closure $thread = null): Action
    {
        $resolve = fn (Model $record): ?Model => $thread ? $thread($record) : $record;

        return Action::make('comments')
            ->label('Messages')
            ->iconButton()
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color(fn (Model $record): string => static::unread($resolve($record)) > 0 ? 'primary' : 'gray')
            ->badge(fn (Model $record): ?int => static::unread($resolve($record)) ?: null)
            ->badgeColor('danger')
            ->visible(fn (Model $record): bool => $resolve($record) !== null)
            ->alpineClickHandler(fn (Model $record): string => sprintf(
                "\$dispatch('open-comments-thread', { type: %s, id: %d })",
                Js::from($resolve($record)->getMorphClass()),
                $resolve($record)->getKey(),
            ));
    }

    /**
     * Memoised per request: the colour and the badge both need it.
     *
     * @param  (Model&HasComments)|null  $record
     */
    protected static function unread(?Model $record): int
    {
        /** @var array<string, int> $cache */
        static $cache = [];

        if ($record === null || auth()->guest()) {
            return 0;
        }

        return $cache[$record->getMorphClass().':'.$record->getKey().':'.auth()->id()] ??= $record->unreadCommentsCountFor(auth()->user());
    }
}
