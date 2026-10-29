@php
    use App\Filament\Support\CommentMentions;
    use Filament\Forms\Components\RichEditor\RichContentRenderer;
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;

    $me = auth()->user();

    $initials = fn (?string $name): string => collect(preg_split('/\s+/', trim($name ?? '?')))
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
        ->join('') ?: '?';

    $avatarColor = fn (?int $id): string => [
        'bg-sky-500', 'bg-violet-500', 'bg-emerald-500', 'bg-rose-500',
        'bg-indigo-500', 'bg-teal-500', 'bg-fuchsia-500', 'bg-cyan-600',
    ][($id ?? 0) % 8];

    $typeIcon = fn (string $type): string => match ($type) {
        'rfp' => 'heroicon-o-document-text',
        'p2p_invoice' => 'heroicon-o-receipt-percent',
        default => 'heroicon-o-chat-bubble-left-right',
    };

    $dayLabel = fn (Carbon $date): string => match (true) {
        $date->isToday() => 'Today',
        $date->isYesterday() => 'Yesterday',
        $date->isCurrentYear() => $date->format('l, M j'),
        default => $date->format('M j, Y'),
    };

    $unreadThreads = $threads->where('unread_comments_count', '>', 0)->count();
@endphp

<div wire:poll.15s.visible="refreshThread" class="fi-comments-chat fixed bottom-6 end-6 z-40 flex flex-col items-end gap-4">
    @if ($open)
        <section
            x-data="{ shown: false }"
            x-init="$nextTick(() => shown = true)"
            x-show="shown"
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="translate-y-3 scale-95 opacity-0"
            x-transition:enter-end="translate-y-0 scale-100 opacity-100"
            class="flex h-[36rem] max-h-[calc(100vh-7.5rem)] w-[26rem] max-w-[calc(100vw-2rem)] origin-bottom-right flex-col overflow-hidden rounded-3xl bg-white shadow-2xl shadow-gray-950/20 ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10"
            aria-label="{{ $thread ? 'Conversation on '.$thread->commentSubject() : 'Messages' }}"
        >
            @if ($thread)
                {{-- Conversation header --}}
                @php
                    $participants = $comments->pluck('user')->filter()->unique('id')->reject(fn ($user) => $user->is($me))->values();
                @endphp

                <header class="flex items-center gap-3 border-b border-gray-950/5 bg-white/90 px-3 py-3 backdrop-blur dark:border-white/10 dark:bg-gray-900/90">
                    <button
                        type="button"
                        wire:click="showInbox"
                        class="flex size-9 shrink-0 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white"
                        aria-label="All messages"
                    >
                        <x-filament::icon icon="heroicon-m-chevron-left" class="size-5" />
                    </button>

                    <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-400 to-primary-600 text-white shadow-sm shadow-primary-600/30">
                        <x-filament::icon :icon="$typeIcon($thread->getMorphClass())" class="size-5" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[0.9375rem] font-semibold leading-tight text-gray-950 dark:text-white">{{ $thread->commentSubject() }}</p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                            {{ $thread::commentTypeLabel() }}
                            @if ($participants->isNotEmpty())
                                · with {{ $participants->take(2)->pluck('name')->join(', ') }}{{ $participants->count() > 2 ? ' +'.($participants->count() - 2) : '' }}
                            @endif
                        </p>
                    </div>

                    @if ($participants->isNotEmpty())
                        <div class="hidden -space-x-2 sm:flex">
                            @foreach ($participants->take(3) as $person)
                                <span
                                    title="{{ $person->name }}"
                                    @class(['flex size-7 items-center justify-center rounded-full text-[0.625rem] font-semibold text-white ring-2 ring-white dark:ring-gray-900', $avatarColor($person->id)])
                                >{{ $initials($person->name) }}</span>
                            @endforeach
                        </div>
                    @endif

                    <a
                        href="{{ $thread->commentUrlFor($me) }}"
                        class="flex size-9 shrink-0 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white"
                        title="Open {{ $thread::commentTypeLabel() }}"
                    >
                        <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="size-4.5" />
                    </a>
                </header>

                {{-- Messages --}}
                <div
                    x-data
                    x-init="$el.scrollTop = $el.scrollHeight"
                    x-on:comments-chat-scroll.window="$nextTick(() => $el.scrollTo({ top: $el.scrollHeight, behavior: 'smooth' }))"
                    class="flex-1 overflow-y-auto bg-gray-50 px-4 pt-2 pb-4 dark:bg-gray-950/40"
                >
                    @php
                        $previous = null;
                    @endphp

                    @forelse ($comments as $comment)
                        @php
                            $mine = $comment->user_id === $me->id;
                            $newDay = ! $previous || ! $previous->created_at->isSameDay($comment->created_at);
                            $grouped = ! $newDay && $previous->user_id === $comment->user_id
                                && $previous->is_internal === $comment->is_internal
                                && $previous->created_at->diffInMinutes($comment->created_at) < 5;
                            $next = $comments->get($loop->index + 1);
                            $lastInGroup = ! $next || $next->user_id !== $comment->user_id
                                || ! $next->created_at->isSameDay($comment->created_at)
                                || $next->is_internal !== $comment->is_internal
                                || $comment->created_at->diffInMinutes($next->created_at) >= 5;
                        @endphp

                        @if ($newDay)
                            <div class="sticky top-0 z-10 flex justify-center py-3">
                                <span class="rounded-full bg-white/90 px-3 py-1 text-[0.6875rem] font-medium text-gray-500 shadow-sm ring-1 ring-gray-950/5 backdrop-blur dark:bg-gray-800/90 dark:text-gray-400 dark:ring-white/10">
                                    {{ $dayLabel($comment->created_at) }}
                                </span>
                            </div>
                        @endif

                        <div
                            wire:key="comment-{{ $comment->id }}"
                            @class(['flex items-end gap-2', 'flex-row-reverse' => $mine, $grouped ? 'mt-0.5' : 'mt-3'])
                        >
                            {{-- Avatar slot keeps grouped bubbles aligned --}}
                            @unless ($mine)
                                <div class="w-7 shrink-0">
                                    @if ($lastInGroup)
                                        <span
                                            title="{{ $comment->user->name }}"
                                            @class(['flex size-7 items-center justify-center rounded-full text-[0.625rem] font-semibold text-white', $avatarColor($comment->user_id)])
                                        >{{ $initials($comment->user->name) }}</span>
                                    @endif
                                </div>
                            @endunless

                            <div @class(['flex max-w-[78%] min-w-0 flex-col', 'items-end' => $mine, 'items-start' => ! $mine])>
                                @if (! $grouped && ! $mine)
                                    <p class="mb-1 ps-3 text-[0.6875rem] font-medium text-gray-500 dark:text-gray-400">{{ $comment->user->name }}</p>
                                @endif

                                @if (! $grouped && $comment->is_internal)
                                    <p class="mb-1 flex items-center gap-1 px-3 text-[0.6875rem] font-medium text-gray-500 dark:text-gray-400">
                                        <x-filament::icon icon="heroicon-m-lock-closed" class="size-3" />
                                        Internal note · hidden from vendor
                                    </p>
                                @endif

                                <div
                                    title="{{ $comment->created_at->format('M j, Y g:i A') }}"
                                    @class([
                                        'fi-chat-bubble px-3.5 py-2 text-sm leading-relaxed break-words',
                                        'fi-chat-bubble-mine' => $mine && ! $comment->is_internal,
                                        'fi-chat-bubble-internal' => $comment->is_internal,
                                        'rounded-[1.25rem]' => true,
                                        'rounded-br-md' => $mine && $lastInGroup,
                                        'rounded-tr-md' => $mine && $grouped,
                                        'rounded-bl-md' => ! $mine && $lastInGroup,
                                        'rounded-tl-md' => ! $mine && $grouped,
                                        'bg-gradient-to-br from-primary-500 to-primary-600 text-white shadow-sm shadow-primary-600/20' => $mine && ! $comment->is_internal,
                                        'bg-white text-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-800 dark:text-gray-100 dark:ring-white/10' => ! $mine && ! $comment->is_internal,
                                        'border border-dashed border-gray-300 bg-gray-100/80 text-gray-800 dark:border-white/20 dark:bg-white/5 dark:text-gray-200' => $comment->is_internal,
                                    ])
                                >
                                    <div class="fi-chat-bubble-body">
                                        {!! RichContentRenderer::make($comment->body)->mentions([CommentMentions::provider()])->toHtml() !!}
                                    </div>

                                    @if (filled($comment->attachments))
                                        <div class="mt-2 space-y-1.5">
                                            @foreach ($comment->attachments as $path)
                                                @php
                                                    $extension = Str::upper(pathinfo($path, PATHINFO_EXTENSION));
                                                @endphp

                                                <a
                                                    href="{{ Storage::disk('local')->temporaryUrl($path, now()->addMinutes(30)) }}"
                                                    target="_blank"
                                                    @class([
                                                        'flex items-center gap-2.5 rounded-xl p-2 transition',
                                                        'bg-white/15 hover:bg-white/25' => $mine && ! $comment->is_internal,
                                                        'bg-gray-50 ring-1 ring-gray-950/5 hover:bg-gray-100 dark:bg-white/5 dark:ring-white/10 dark:hover:bg-white/10' => ! $mine || $comment->is_internal,
                                                    ])
                                                >
                                                    <span @class([
                                                        'flex size-8 shrink-0 items-center justify-center rounded-lg',
                                                        'bg-white/20' => $mine && ! $comment->is_internal,
                                                        'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' => ! $mine || $comment->is_internal,
                                                    ])>
                                                        <x-filament::icon :icon="in_array($extension, ['JPG', 'JPEG', 'PNG', 'GIF', 'WEBP']) ? 'heroicon-m-photo' : 'heroicon-m-document'" class="size-4" />
                                                    </span>
                                                    <span class="min-w-0 flex-1">
                                                        <span class="block truncate text-xs font-medium">{{ basename($path) }}</span>
                                                        <span class="block text-[0.625rem] opacity-70">{{ $extension }} file</span>
                                                    </span>
                                                    <x-filament::icon icon="heroicon-m-arrow-down-tray" class="size-4 shrink-0 opacity-70" />
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                @if ($lastInGroup)
                                    <p @class(['mt-1 px-1 text-[0.625rem] text-gray-400 dark:text-gray-500', 'text-end' => $mine])>
                                        {{ $comment->created_at->format('g:i A') }}
                                        @if ($mine && $loop->last)
                                            @if ($seenBy->isNotEmpty())
                                                · <span class="font-medium text-primary-600 dark:text-primary-400">Seen by {{ $seenBy->count() === 1 ? $seenBy->first()->name : $seenBy->count().' people' }}</span>
                                            @else
                                                · Sent
                                            @endif
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>

                        @php
                            $previous = $comment;
                        @endphp
                    @empty
                        <div class="flex h-full flex-col items-center justify-center px-8 text-center">
                            <div class="mb-4 flex size-16 items-center justify-center rounded-3xl bg-gradient-to-br from-primary-100 to-primary-200 text-primary-600 dark:from-primary-500/20 dark:to-primary-500/5 dark:text-primary-400">
                                <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="size-8" />
                            </div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">Start the conversation</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Ask a question about {{ $thread->commentSubject() }}. Type @ to bring someone in.</p>
                        </div>
                    @endforelse
                </div>

                {{-- Composer --}}
                <form
                    wire:submit="send"
                    x-on:keydown.meta.enter.prevent="$wire.send()"
                    x-on:keydown.ctrl.enter.prevent="$wire.send()"
                    class="fi-chat-composer border-t border-gray-950/5 bg-white px-3 pt-3 pb-3 dark:border-white/10 dark:bg-gray-900"
                >
                    <div class="rounded-2xl bg-gray-100 px-1 pt-1 transition focus-within:bg-white focus-within:ring-2 focus-within:ring-primary-500/40 dark:bg-white/5 dark:focus-within:bg-gray-900">
                        {{ $this->form }}

                        <div class="flex items-center gap-1 px-1 pb-1.5">
                            <button
                                type="button"
                                wire:click="$toggle('showAttachments')"
                                @class([
                                    'flex size-8 items-center justify-center rounded-full transition',
                                    'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300' => $showAttachments,
                                    'text-gray-500 hover:bg-gray-200 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-white' => ! $showAttachments,
                                ])
                                aria-label="Attach files"
                            >
                                <x-filament::icon icon="heroicon-m-paper-clip" class="size-4.5" />
                            </button>

                            <span class="ms-1 hidden text-[0.625rem] text-gray-400 sm:inline dark:text-gray-500">⌘/Ctrl + Enter to send</span>

                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                wire:target="send"
                                class="ms-auto flex size-9 items-center justify-center rounded-full bg-gradient-to-br from-primary-500 to-primary-600 text-white shadow-sm shadow-primary-600/30 transition hover:scale-105 active:scale-95 disabled:opacity-60"
                                aria-label="Send"
                            >
                                <x-filament::icon icon="heroicon-m-paper-airplane" class="size-4" wire:loading.remove wire:target="send" />
                                <x-filament::loading-indicator class="size-4" wire:loading wire:target="send" />
                            </button>
                        </div>
                    </div>
                </form>
            @else
                {{-- Inbox --}}
                @php
                    $inboxItems = $threads->map(fn ($item): array => [
                        'unread' => $item->unread_comments_count > 0,
                        'text' => Str::lower($item->commentSubject().' '.$item::commentTypeLabel().' '.$item->latestComment?->user?->name.' '.$item->latestComment?->plainText()),
                    ])->values();
                @endphp

                <div
                    x-data="{
                        search: '',
                        unreadOnly: false,
                        items: @js($inboxItems),
                        shows(index) {
                            const item = this.items[index]

                            return (! this.unreadOnly || item.unread) && item.text.includes(this.search.trim().toLowerCase())
                        },
                        get matches() {
                            return this.items.filter((item, index) => this.shows(index)).length
                        },
                    }"
                    class="flex min-h-0 flex-1 flex-col"
                >
                    <header class="relative overflow-hidden bg-gradient-to-br from-primary-500 via-primary-600 to-primary-700 px-5 pt-5 pb-4 text-white">
                        <div class="pointer-events-none absolute -end-10 -top-12 size-40 rounded-full bg-white/10"></div>
                        <div class="pointer-events-none absolute -bottom-16 end-16 size-32 rounded-full bg-white/5"></div>

                        <div class="relative flex items-start justify-between gap-3">
                            <div>
                                <p class="text-lg leading-tight font-bold tracking-tight">Messages</p>
                                <p class="mt-0.5 text-xs text-white/80">
                                    {{ $unreadThreads ? $unreadThreads.' '.Str::plural('conversation', $unreadThreads).' with new messages' : 'You’re all caught up' }}
                                </p>
                            </div>

                            <button
                                type="button"
                                wire:click="toggle"
                                class="flex size-8 items-center justify-center rounded-full bg-white/15 transition hover:bg-white/25"
                                aria-label="Close"
                            >
                                <x-filament::icon icon="heroicon-m-x-mark" class="size-4.5" />
                            </button>
                        </div>

                        <div class="relative mt-4 flex items-center gap-2">
                            <label class="flex flex-1 items-center gap-2 rounded-full bg-white/15 px-3 py-1.5 ring-1 ring-white/20 transition focus-within:bg-white/25">
                                <x-filament::icon icon="heroicon-m-magnifying-glass" class="size-4 text-white/80" />
                                <input
                                    x-model="search"
                                    type="search"
                                    placeholder="Search conversations"
                                    class="w-full border-0 bg-transparent p-0 text-sm text-white placeholder:text-white/70 focus:ring-0 focus:outline-none"
                                />
                            </label>

                            <div class="flex rounded-full bg-white/15 p-0.5 text-xs font-medium ring-1 ring-white/20">
                                <button type="button" x-on:click="unreadOnly = false" x-bind:class="unreadOnly ? 'text-white/80' : 'bg-white text-primary-700 shadow-sm'" class="rounded-full px-2.5 py-1 transition">All</button>
                                <button type="button" x-on:click="unreadOnly = true" x-bind:class="unreadOnly ? 'bg-white text-primary-700 shadow-sm' : 'text-white/80'" class="rounded-full px-2.5 py-1 transition">Unread</button>
                            </div>
                        </div>
                    </header>

                    <ul class="flex-1 overflow-y-auto p-2">
                        @forelse ($threads as $item)
                            @php
                                $latest = $item->latestComment;
                                $hasUnread = $item->unread_comments_count > 0;
                            @endphp

                            <li wire:key="thread-{{ $item->getMorphClass() }}-{{ $item->getKey() }}" x-show="shows({{ $loop->index }})">
                                <button
                                    type="button"
                                    wire:click="openThread(@js($item->getMorphClass()), {{ $item->getKey() }})"
                                    @class([
                                        'group flex w-full items-center gap-3 rounded-2xl px-3 py-2.5 text-start transition',
                                        'bg-primary-50/70 hover:bg-primary-50 dark:bg-primary-500/10 dark:hover:bg-primary-500/15' => $hasUnread,
                                        'hover:bg-gray-50 dark:hover:bg-white/5' => ! $hasUnread,
                                    ])
                                >
                                    <div class="relative shrink-0">
                                        <span @class(['flex size-11 items-center justify-center rounded-full text-sm font-semibold text-white', $avatarColor($latest?->user_id)])>
                                            {{ $initials($latest?->user?->name) }}
                                        </span>
                                        <span class="absolute -end-1 -bottom-1 flex size-5 items-center justify-center rounded-full bg-white text-primary-600 shadow ring-1 ring-gray-950/5 dark:bg-gray-800 dark:text-primary-400 dark:ring-white/10">
                                            <x-filament::icon :icon="$typeIcon($item->getMorphClass())" class="size-3" />
                                        </span>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-baseline justify-between gap-2">
                                            <p @class(['truncate text-sm text-gray-950 dark:text-white', 'font-semibold' => $hasUnread, 'font-medium' => ! $hasUnread])>
                                                {{ $item->commentSubject() }}
                                            </p>
                                            <span @class(['shrink-0 text-[0.6875rem]', 'font-semibold text-primary-600 dark:text-primary-400' => $hasUnread, 'text-gray-400 dark:text-gray-500' => ! $hasUnread])>
                                                {{ $latest?->created_at->isToday() ? $latest->created_at->format('g:i A') : $latest?->created_at->diffForHumans(short: true) }}
                                            </span>
                                        </div>

                                        <div class="mt-0.5 flex items-center gap-2">
                                            <p @class(['min-w-0 flex-1 truncate text-xs', 'text-gray-700 dark:text-gray-200' => $hasUnread, 'text-gray-500 dark:text-gray-400' => ! $hasUnread])>
                                                @if ($latest?->is_internal)
                                                    <x-filament::icon icon="heroicon-m-lock-closed" class="mb-0.5 inline size-3" />
                                                @endif
                                                <span class="font-medium">{{ $latest?->user_id === $me->id ? 'You' : Str::before($latest?->user?->name ?? '', ' ') }}:</span>
                                                {{ Str::limit($latest?->plainText() ?? '', 80) }}
                                            </p>

                                            @if ($hasUnread)
                                                <span class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-primary-600 px-1.5 text-[0.6875rem] font-semibold text-white">
                                                    {{ $item->unread_comments_count }}
                                                </span>
                                            @endif
                                        </div>

                                        <p class="mt-0.5 text-[0.625rem] font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">{{ $item::commentTypeLabel() }}</p>
                                    </div>
                                </button>
                            </li>
                        @empty
                            <li class="flex h-full flex-col items-center justify-center px-8 py-12 text-center">
                                <div class="mb-4 flex size-16 items-center justify-center rounded-3xl bg-gradient-to-br from-primary-100 to-primary-200 text-primary-600 dark:from-primary-500/20 dark:to-primary-500/5 dark:text-primary-400">
                                    <x-filament::icon icon="heroicon-o-inbox" class="size-8" />
                                </div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">No conversations yet</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Open an RFP or invoice, or tap a row’s chat icon, to start one.</p>
                            </li>
                        @endforelse

                        @if ($threads->isNotEmpty())
                            <li x-cloak x-show="matches === 0" class="px-8 py-10 text-center text-xs text-gray-500 dark:text-gray-400">
                                <span x-text="unreadOnly && ! search ? 'No unread messages. You’re all caught up.' : 'No conversations match.'"></span>
                            </li>
                        @endif
                    </ul>
                </div>
            @endif
        </section>
    @endif

    {{-- Launcher --}}
    <button
        type="button"
        wire:click="toggle"
        data-tour="messages"
        class="group relative flex size-14 items-center justify-center rounded-full bg-gradient-to-br from-primary-500 to-primary-600 text-white shadow-xl shadow-primary-600/30 ring-4 ring-white/60 transition duration-200 hover:scale-105 hover:shadow-primary-600/40 active:scale-95 focus:outline-none focus-visible:ring-primary-300 dark:ring-gray-900/60"
        aria-label="{{ $open ? 'Close messages' : 'Open messages' }}"
    >
        @if ($unread > 0 && ! $open)
            <span class="absolute inset-0 animate-ping rounded-full bg-primary-500 opacity-30"></span>
        @endif

        <x-filament::icon
            :icon="$open ? 'heroicon-m-chevron-down' : 'heroicon-s-chat-bubble-oval-left-ellipsis'"
            class="relative size-7 transition duration-200 group-hover:scale-110"
        />

        @if ($unread > 0)
            <span class="absolute -end-0.5 -top-0.5 flex h-5.5 min-w-5.5 items-center justify-center rounded-full bg-danger-600 px-1.5 text-[0.6875rem] font-bold text-white ring-2 ring-white dark:ring-gray-900">
                {{ $unread > 99 ? '99+' : $unread }}
            </span>
        @endif
    </button>

    <x-filament-actions::modals />
</div>
