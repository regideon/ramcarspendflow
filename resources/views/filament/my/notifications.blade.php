@php
    use App\Filament\My\Pages\Profile;
    use App\Filament\My\Pages\RfpDetail;
    use App\Filament\My\Support\SampleNotifications;
    use Filament\Support\Icons\Heroicon;

    $groups = SampleNotifications::grouped($user);
    $all = $groups->flatten(1);

    $types = [
        'waiting' => ['icon' => Heroicon::OutlinedCheckBadge, 'tone' => 'border-indigo-100 bg-indigo-50 text-indigo-600'],
        'mention' => ['icon' => Heroicon::OutlinedAtSymbol, 'tone' => 'border-violet-100 bg-violet-50 text-violet-600'],
        'returned' => ['icon' => Heroicon::OutlinedArrowUturnLeft, 'tone' => 'border-orange-100 bg-orange-50 text-orange-600'],
        'approved' => ['icon' => Heroicon::OutlinedCheckCircle, 'tone' => 'border-emerald-100 bg-emerald-50 text-emerald-600'],
        'paid' => ['icon' => Heroicon::OutlinedBanknotes, 'tone' => 'border-emerald-100 bg-emerald-50 text-emerald-600'],
        'rejected' => ['icon' => Heroicon::OutlinedXCircle, 'tone' => 'border-rose-100 bg-rose-50 text-rose-600'],
    ];

    $categories = collect(SampleNotifications::CATEGORIES)
        ->filter(fn (string $label, string $key): bool => $key === 'all' || $all->contains('category', $key));
@endphp

{{-- Notifications: slides in from the right; full screen on phones. Opened by the `open-notifications` event. --}}
<div
    x-data="{
        open: false,
        filter: @js($categories->keys()->first()),
        unreadIds: @js($all->where('unread', true)->pluck('id')->values()),
        categories: @js($all->pluck('category', 'id')),
        shows(category) {
            return this.filter === 'all' || this.filter === category
        },
        groupShows(ids) {
            return ids.some((id) => this.shows(this.categories[id]))
        },
        get visibleCount() {
            return Object.values(this.categories).filter((category) => this.shows(category)).length
        },
        isUnread(id) {
            return this.unreadIds.includes(id)
        },
        read(id) {
            this.unreadIds = this.unreadIds.filter((unread) => unread !== id)
        },
        unreadIn(category) {
            return this.unreadIds.filter((id) => category === 'all' || this.categories[id] === category).length
        },
    }"
    x-init="$watch('unreadIds', (ids) => $dispatch('notifications-unread', ids.length)); $watch('open', (value) => document.body.classList.toggle('overflow-hidden', value))"
    x-on:open-notifications.window="open = true"
    x-on:keydown.escape.window="open = false"
>
    <div x-cloak x-show="open" class="fixed inset-0 z-50">
        <div x-show="open" x-transition.opacity.duration.200ms x-on:click="open = false" class="absolute inset-0 bg-slate-900/25 backdrop-blur-[2px]"></div>

        <aside
            x-show="open"
            x-transition:enter="transition duration-300 ease-out"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition duration-200 ease-in"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            role="dialog"
            aria-modal="true"
            aria-labelledby="notifications-title"
            class="absolute inset-y-0 right-0 flex w-[94%] flex-col overflow-hidden rounded-l-[28px] border-l border-white bg-[#f5f6fb]/95 shadow-2xl backdrop-blur-2xl sm:max-w-[26rem]"
        >
            <div class="pointer-events-none absolute -top-24 -right-20 size-72 rounded-full bg-indigo-200/50 blur-3xl"></div>
            <div class="pointer-events-none absolute bottom-0 -left-24 size-72 rounded-full bg-sky-100/60 blur-3xl"></div>

            {{-- Header --}}
            <header class="relative px-5 pt-[max(1.25rem,env(safe-area-inset-top))] pb-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="notifications-title" class="font-display text-xl font-bold tracking-tight text-slate-900">Notifications</h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            <span x-show="unreadIds.length" x-text="unreadIds.length + ' new'"></span>
                            <span x-show="! unreadIds.length">You’re all caught up</span>
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" x-show="unreadIds.length" x-on:click="unreadIds = []" class="rounded-full px-2.5 py-1.5 text-xs font-semibold text-indigo-600 transition hover:bg-white/70">
                            Mark all read
                        </button>
                        <button type="button" x-on:click="open = false" aria-label="Close" class="my-glass flex size-9 items-center justify-center rounded-full text-slate-600 transition hover:text-slate-900 active:scale-95">
                            <x-filament::icon :icon="Heroicon::XMark" class="size-5" />
                        </button>
                    </div>
                </div>

                <div class="-mx-5 mt-4 flex gap-2 overflow-x-auto px-5 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach ($categories as $key => $label)
                        <button
                            type="button"
                            x-on:click="filter = @js($key)"
                            x-bind:class="filter === @js($key) ? 'bg-slate-900 text-white shadow-sm' : 'my-glass text-slate-600 hover:text-slate-900'"
                            class="flex shrink-0 items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-semibold transition"
                        >
                            {{ $label }}
                            <span
                                x-show="unreadIn(@js($key))"
                                x-text="unreadIn(@js($key))"
                                x-bind:class="filter === @js($key) ? 'bg-white/20 text-white' : 'bg-indigo-600 text-white'"
                                class="flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] leading-none font-bold"
                            ></span>
                        </button>
                    @endforeach
                </div>
            </header>

            {{-- List --}}
            <div class="relative flex-1 overflow-y-auto px-4 pb-6">
                @foreach ($groups as $day => $items)
                    <section x-show="groupShows(@js($items->pluck('id')))" class="mt-3 first:mt-1">
                        <h3 class="mb-2 px-1 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">{{ $day }}</h3>

                        <div class="flex flex-col gap-2">
                            @foreach ($items as $item)
                                @php
                                    $type = $types[$item['type']];
                                    $url = RfpDetail::getUrl(['number' => $item['number']]);
                                @endphp

                                <a
                                    href="{{ $url }}"
                                    x-show="shows(@js($item['category']))"
                                    x-on:click="read(@js($item['id']))"
                                    x-bind:class="isUnread(@js($item['id'])) ? 'bg-white/90 ring-indigo-100 shadow-[0_8px_24px_-12px_rgba(79,70,229,0.25)]' : 'bg-white/50 ring-white/80'"
                                    class="group relative flex gap-3 rounded-2xl p-3.5 ring-1 transition hover:bg-white"
                                >
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl border {{ $type['tone'] }}">
                                        <x-filament::icon :icon="$type['icon']" class="size-5" />
                                    </span>

                                    <span class="min-w-0 flex-1">
                                        <span class="flex items-start justify-between gap-2">
                                            <span x-bind:class="isUnread(@js($item['id'])) ? 'font-semibold text-slate-900' : 'font-medium text-slate-700'" class="text-sm leading-snug">{{ $item['title'] }}</span>
                                            <span x-show="isUnread(@js($item['id']))" class="mt-1.5 size-2 shrink-0 rounded-full bg-indigo-600"></span>
                                        </span>

                                        <span class="mt-0.5 line-clamp-2 block text-xs leading-relaxed text-slate-500">{{ $item['body'] }}</span>

                                        <span class="mt-2 flex items-center justify-between gap-2">
                                            <span class="flex min-w-0 items-center gap-1.5 text-[11px] text-slate-400">
                                                <span class="truncate font-mono">{{ $item['number'] }}</span>
                                                <span>·</span>
                                                <span class="shrink-0">{{ match (true) { $item['at']->isToday() => $item['at']->diffForHumans(short: true), $item['at']->isYesterday() => $item['at']->format('g:i A'), default => $item['at']->format('D, M j') } }}</span>
                                            </span>

                                            @if ($item['action'])
                                                <span class="shrink-0 rounded-full bg-slate-900 px-3 py-1 text-[11px] font-semibold text-white transition group-hover:bg-slate-800">{{ $item['action'] }}</span>
                                            @endif
                                        </span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                {{-- Empty state for a filter --}}
                <div x-show="! visibleCount" x-cloak class="flex flex-col items-center px-8 py-16 text-center">
                    <span class="flex size-14 items-center justify-center rounded-2xl bg-white/80 text-slate-400 ring-1 ring-white">
                        <x-filament::icon :icon="Heroicon::OutlinedBellSlash" class="size-7" />
                    </span>
                    <p class="mt-4 text-sm font-semibold text-slate-900">Nothing here</p>
                    <p class="mt-1 text-xs text-slate-500">You’re all caught up.</p>
                </div>
            </div>

            {{-- Footer --}}
            <footer class="relative border-t border-white/80 bg-white/60 px-5 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur-xl">
                <a href="{{ Profile::getUrl() }}" class="flex items-center justify-between rounded-xl px-1 py-1.5 text-xs font-semibold text-slate-600 transition hover:text-slate-900">
                    <span class="flex items-center gap-2">
                        <x-filament::icon :icon="Heroicon::OutlinedCog6Tooth" class="size-4" />
                        Notification settings
                    </span>
                    <x-filament::icon :icon="Heroicon::ChevronRight" class="size-4 text-slate-400" />
                </a>
            </footer>
        </aside>
    </div>
</div>
