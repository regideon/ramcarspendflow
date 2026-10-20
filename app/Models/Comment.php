<?php

namespace App\Models;

use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A comment on a record (RFP, later P2P). The body is rich-editor HTML and may @mention users.
 * Never changes the record's status. Internal comments are hidden from vendors.
 */
#[Fillable(['user_id', 'body', 'attachments', 'is_internal'])]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    /**
     * The record being discussed.
     *
     * @return MorphTo<Model, $this>
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The body as plain text for previews and notifications: no tags, entities decoded
     * (so "I&#039;ll" reads "I'll"). Escape it again when printing.
     */
    public function plainText(): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('</p><p>', '</p> <p>', $this->body)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /**
     * Ids of the users @mentioned in the body.
     *
     * @return list<int>
     */
    public function mentionedUserIds(): array
    {
        preg_match_all('/<[^>]*data-type="mention"[^>]*>/', $this->body, $tags);

        return collect($tags[0])
            ->map(fn (string $tag): ?int => preg_match('/data-id="(\d+)"/', $tag, $id) ? (int) $id[1] : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'is_internal' => 'boolean',
        ];
    }
}
