<?php

namespace App\Filament\Support;

use App\Models\Concerns\HasComments;
use App\Models\User;
use Filament\Forms\Components\RichEditor\MentionProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * The @ picker for comments: only people who can open the record. Also renders stored mentions as names.
 */
class CommentMentions
{
    /**
     * @param  (Model&HasComments)|null  $record
     */
    public static function provider(?Model $record = null): MentionProvider
    {
        return MentionProvider::make('@')
            ->getSearchResultsUsing(fn (string $search): array => ($record?->mentionableUsers() ?? User::query()->whereRaw('0 = 1'))
                ->where('name', 'like', "%{$search}%")
                ->orderBy('name')
                ->limit(10)
                ->pluck('name', 'id')
                ->all())
            ->getLabelsUsing(fn (array $ids): array => User::query()->whereKey($ids)->pluck('name', 'id')->all());
    }
}
