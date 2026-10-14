<?php

namespace App\Livewire;

use App\Filament\Resources\Rfps\Schemas\RfpForm;
use App\Filament\Support\CommentMentions;
use App\Models\Concerns\HasComments;
use App\Models\User;
use App\Services\Comments\CommentInbox;
use App\Services\Comments\PostComment;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Floating chat bubble (bottom right). On a record page it opens that record's thread; elsewhere
 * it opens an inbox of the user's conversations (RFP and P2P), and a row's chat icon opens a
 * thread directly. Polls for new messages and shows an unread badge while closed.
 */
class CommentsChat extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions, InteractsWithSchemas;

    /** The record of the page the bubble sits on, if any. */
    #[Locked]
    public ?string $pageType = null;

    #[Locked]
    public ?int $pageId = null;

    /** The open thread; null shows the inbox. */
    #[Locked]
    public ?string $type = null;

    #[Locked]
    public ?int $id = null;

    public bool $open = false;

    public bool $showAttachments = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var (Model&HasComments)|false|null */
    protected Model|false|null $resolvedThread = null;

    public function mount(?Model $record = null): void
    {
        if ($record !== null && CommentInbox::classFor($record->getMorphClass()) && $record->canBeCommentedOnBy($this->user())) {
            $this->type = $this->pageType = $record->getMorphClass();
            $this->id = $this->pageId = $record->getKey();
        }

        $this->resetForm();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                RichEditor::make('body')
                    ->hiddenLabel()
                    ->placeholder('Write a message. Type @ to mention someone.')
                    ->required()
                    ->toolbarButtons([['bold', 'italic', 'link', 'bulletList']])
                    ->mentions(fn (): array => [CommentMentions::provider($this->thread())]),
                FileUpload::make('attachments')
                    ->hiddenLabel()
                    ->multiple()
                    ->disk('local')
                    ->directory(fn (): string => PostComment::attachmentDirectory($this->thread()))
                    ->visibility('private')
                    ->acceptedFileTypes(RfpForm::DOCUMENT_TYPES)
                    ->maxSize(10_240)
                    ->maxFiles(5)
                    ->visible(fn (): bool => $this->showAttachments),
                Toggle::make('is_internal')
                    ->label('Internal: hide from the vendor')
                    ->visible(fn (): bool => $this->canPostInternal()),
            ])
            ->statePath('data');
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;

        if ($this->open) {
            $this->markRead();
            $this->dispatch('comments-chat-scroll');
        } else {
            $this->showThread($this->pageType, $this->pageId);
        }
    }

    /**
     * Opens a thread from the inbox or from a table row's chat icon.
     */
    #[On('open-comments-thread')]
    public function openThread(string $type, int $id): void
    {
        $class = CommentInbox::classFor($type);

        abort_unless($class && $class::query()->commentableBy($this->user())->whereKey($id)->exists(), 403);

        $this->showThread($type, $id);
        $this->open = true;
        $this->markRead();
        $this->dispatch('comments-chat-scroll');
    }

    public function showInbox(): void
    {
        $this->showThread(null, null);
    }

    /**
     * Runs on each poll: while a thread is open, new messages count as read.
     */
    public function refreshThread(): void
    {
        if ($this->open) {
            $this->markRead();
        }
    }

    public function send(): void
    {
        $thread = $this->thread();

        abort_unless($thread, 403);

        app(PostComment::class)->handle($thread, $this->user(), $this->form->getState());

        $this->resetForm();

        $this->dispatch('comment-posted');
        $this->dispatch('comments-chat-scroll');
    }

    public function render(): View
    {
        $thread = $this->thread();
        $inbox = app(CommentInbox::class);

        return view('filament.livewire.comments-chat', [
            'thread' => $thread,
            'comments' => $this->open && $thread ? $thread->commentsVisibleTo($this->user())->with('user')->get() : collect(),
            'threads' => $this->open && ! $thread ? $inbox->threads($this->user()) : collect(),
            'unread' => match (true) {
                $this->open => 0,
                $this->pageType !== null => $thread?->unreadCommentsCountFor($this->user()) ?? 0,
                default => $inbox->unreadTotal($this->user()),
            },
            'canPostInternal' => $this->canPostInternal(),
        ]);
    }

    /**
     * @return (Model&HasComments)|null
     */
    protected function thread(): ?Model
    {
        if ($this->resolvedThread === null) {
            $class = $this->type ? CommentInbox::classFor($this->type) : null;

            $this->resolvedThread = $class ? ($class::query()->commentableBy($this->user())->find($this->id) ?? false) : false;
        }

        return $this->resolvedThread ?: null;
    }

    protected function showThread(?string $type, ?int $id): void
    {
        $this->type = $type;
        $this->id = $id;
        $this->resolvedThread = null;
        $this->resetForm();
    }

    protected function markRead(): void
    {
        $this->thread()?->markCommentsReadBy($this->user());
    }

    protected function canPostInternal(): bool
    {
        $thread = $this->thread();

        return $thread !== null && $thread::supportsInternalComments() && $thread::seesInternalComments($this->user());
    }

    protected function resetForm(): void
    {
        $this->showAttachments = false;
        $this->form->fill(['is_internal' => true]);
    }

    protected function user(): User
    {
        return auth()->user();
    }
}
