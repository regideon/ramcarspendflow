<?php

namespace App\Filament\Concerns;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

/**
 * Adds the floating comments chat to a page. On a record page it opens the record's thread;
 * elsewhere it opens the user's inbox. Re-renders the page when a message is sent from the chat,
 * so a Comments section or a row's unread badge stays current.
 */
trait HasCommentsChat
{
    public function getFooter(): ?View
    {
        return auth()->check()
            ? view('filament.comments.chat-footer', ['record' => $this->commentsChatRecord()])
            : null;
    }

    #[On('comment-posted')]
    public function refreshComments(): void
    {
        $record = $this->commentsChatRecord();

        if ($record?->relationLoaded('comments')) {
            $record->load('comments.user');
        }
    }

    /**
     * The record whose thread the bubble opens; null on list and create pages.
     */
    protected function commentsChatRecord(): ?Model
    {
        return property_exists($this, 'record') && $this->record instanceof Model ? $this->record : null;
    }
}
