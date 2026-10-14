@php
    use App\Filament\Support\CommentMentions;
    use Filament\Forms\Components\RichEditor\RichContentRenderer;
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;
@endphp

<div wire:poll.15s.visible="refreshThread" class="fixed bottom-6 end-6 z-40 flex flex-col items-end gap-3">
    @if ($open)
        <section
            class="flex h-[32rem] max-h-[calc(100vh-8rem)] w-[24rem] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            aria-label="{{ $thread ? 'Comments on '.$thread->commentSubject() : 'Messages' }}"
        >
            <header class="flex items-center gap-2 border-b border-gray-200 px-3 py-3 dark:border-white/10">
                @if ($thread)
                    <x-filament::icon-button icon="heroicon-m-arrow-left" color="gray" label="All messages" wire:click="showInbox" />

                    <div class="min-w-0 flex-1">
                        <a href="{{ $thread->commentUrlFor(auth()->user()) }}" class="block truncate text-sm font-semibold text-gray-950 hover:underline dark:text-white">
                            {{ $thread->commentSubject() }}
                        </a>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Posting does not change the status</p>
                    </div>
                @else
                    <div class="min-w-0 flex-1 ps-1">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">Messages</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Conversations on your RFPs and invoices</p>
                    </div>
                @endif

                <x-filament::icon-button icon="heroicon-m-x-mark" color="gray" label="Close" wire:click="toggle" />
            </header>

            @if ($thread)
                <div
                    x-data
                    x-init="$el.scrollTop = $el.scrollHeight"
                    x-on:comments-chat-scroll.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
                    class="flex-1 space-y-3 overflow-y-auto px-4 py-3"
                >
                    @forelse ($comments as $comment)
                        @php($mine = $comment->user_id === auth()->id())

                        <div @class(['flex flex-col', 'items-end' => $mine, 'items-start' => ! $mine]) wire:key="comment-{{ $comment->id }}">
                            <p class="mb-1 flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $mine ? 'You' : $comment->user->name }}
                                · <span title="{{ $comment->created_at->format('M j, Y g:i A') }}">{{ $comment->created_at->diffForHumans() }}</span>
                                @if ($comment->is_internal)
                                    <x-filament::badge color="warning" size="sm" icon="heroicon-m-lock-closed">Internal</x-filament::badge>
                                @endif
                            </p>

                            <div @class([
                                'max-w-[85%] rounded-2xl px-3 py-2 text-sm',
                                'rounded-br-sm bg-primary-600 text-white [&_a]:text-white [&_a]:underline' => $mine,
                                'rounded-bl-sm bg-gray-100 text-gray-950 dark:bg-white/10 dark:text-white' => ! $mine,
                                'ring-2 ring-warning-400' => $comment->is_internal,
                            ])>
                                <div class="prose prose-sm max-w-none text-inherit [&_*]:text-inherit">
                                    {!! RichContentRenderer::make($comment->body)->mentions([CommentMentions::provider()])->toHtml() !!}
                                </div>

                                @foreach ($comment->attachments ?? [] as $path)
                                    <a
                                        href="{{ Storage::disk('local')->temporaryUrl($path, now()->addMinutes(30)) }}"
                                        target="_blank"
                                        class="mt-1 flex items-center gap-1 text-xs underline"
                                    >
                                        <x-filament::icon icon="heroicon-m-paper-clip" class="size-3.5" />
                                        {{ basename($path) }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="flex h-full flex-col items-center justify-center text-center text-sm text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="mb-2 size-8" />
                            No messages yet. Ask a question or @mention someone.
                        </div>
                    @endforelse
                </div>

                <form wire:submit="send" class="space-y-2 border-t border-gray-200 px-3 py-3 dark:border-white/10">
                    {{ $this->form }}

                    <div class="flex items-center justify-between">
                        <x-filament::icon-button
                            icon="heroicon-m-paper-clip"
                            color="gray"
                            label="Attach files"
                            wire:click="$toggle('showAttachments')"
                        />

                        <x-filament::button type="submit" size="sm" icon="heroicon-m-paper-airplane" icon-position="after">
                            Send
                        </x-filament::button>
                    </div>
                </form>
            @else
                <ul class="flex-1 divide-y divide-gray-100 overflow-y-auto dark:divide-white/5">
                    @forelse ($threads as $item)
                        @php($latest = $item->latestComment)

                        <li wire:key="thread-{{ $item->getMorphClass() }}-{{ $item->getKey() }}">
                            <button
                                type="button"
                                wire:click="openThread(@js($item->getMorphClass()), {{ $item->getKey() }})"
                                class="flex w-full items-start gap-3 px-4 py-3 text-start hover:bg-gray-50 dark:hover:bg-white/5"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <p @class(['truncate text-sm text-gray-950 dark:text-white', 'font-semibold' => $item->unread_comments_count > 0])>
                                            {{ $item->commentSubject() }}
                                        </p>
                                        <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $latest?->created_at->diffForHumans(short: true) }}</span>
                                    </div>
                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                        {{ $latest?->user_id === auth()->id() ? 'You' : $latest?->user->name }}:
                                        {{ Str::limit(strip_tags($latest?->body ?? ''), 80) }}
                                    </p>
                                </div>

                                @if ($item->unread_comments_count > 0)
                                    <span class="mt-0.5 flex min-w-5 items-center justify-center rounded-full bg-danger-600 px-1.5 text-xs font-semibold text-white">
                                        {{ $item->unread_comments_count }}
                                    </span>
                                @endif
                            </button>
                        </li>
                    @empty
                        <li class="flex h-full flex-col items-center justify-center px-6 text-center text-sm text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="mb-2 size-8" />
                            No conversations yet. Open an RFP or invoice, or use a row's chat icon, to start one.
                        </li>
                    @endforelse
                </ul>
            @endif
        </section>
    @endif

    <button
        type="button"
        wire:click="toggle"
        class="relative flex size-14 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg transition hover:bg-primary-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
        aria-label="{{ $open ? 'Close messages' : 'Open messages' }}"
    >
        <x-filament::icon :icon="$open ? 'heroicon-o-x-mark' : 'heroicon-o-chat-bubble-left-ellipsis'" class="size-7" />

        @if ($unread > 0)
            <span class="absolute -end-1 -top-1 flex min-w-5 items-center justify-center rounded-full bg-danger-600 px-1.5 text-xs font-semibold text-white ring-2 ring-white dark:ring-gray-900">
                {{ $unread > 99 ? '99+' : $unread }}
            </span>
        @endif
    </button>

    <x-filament-actions::modals />
</div>
