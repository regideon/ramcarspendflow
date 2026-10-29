@php
    use App\Filament\My\Pages\Approvals;
    use App\Filament\My\Pages\CreateRfp;
    use App\Filament\My\Pages\Dashboard;
    use App\Filament\My\Pages\MyRequests;
    use App\Filament\My\Pages\Profile;
    use App\Filament\My\Pages\RfpDetail;
    use App\Filament\My\Support\SampleRfps;
    use App\Livewire\CommentsChat;
    use Filament\Support\Icons\Heroicon;

    $user = filament()->auth()->user();
    $onRfp = $livewire instanceof RfpDetail;
    $onCreate = $livewire instanceof CreateRfp;

    // Messages live in the chat bubble (bottom right), as in the Hub.
    $navigation = [
        ['label' => 'Home', 'icon' => Heroicon::OutlinedHome, 'url' => Dashboard::getUrl(), 'active' => $livewire instanceof Dashboard],
        ['label' => 'Approvals', 'icon' => Heroicon::OutlinedCheckBadge, 'url' => Approvals::getUrl(), 'badge' => SampleRfps::awaitingMyApproval()->count() ?: null, 'active' => $livewire instanceof Approvals || ($onRfp && ! $livewire->isMine()), 'visible' => $user->isMatrixApprover()],
        ['label' => 'Requests', 'icon' => Heroicon::OutlinedDocumentText, 'url' => MyRequests::getUrl(), 'active' => $livewire instanceof MyRequests || ($onRfp && $livewire->isMine())],
        ['label' => 'Profile', 'icon' => Heroicon::OutlinedUserCircle, 'url' => Profile::getUrl(), 'active' => $livewire instanceof Profile],
    ];

    $navigation = array_filter($navigation, fn (array $item): bool => $item['visible'] ?? true);
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    {{-- Aurora glow behind everything --}}
    <div class="my-aurora" aria-hidden="true">
        <span class="-top-20 -left-20 size-80 bg-[#C4B5FD] opacity-50 lg:size-[28rem]"></span>
        <span class="top-1/3 -right-24 h-[340px] w-[300px] bg-[#A5B4FC] opacity-45 lg:h-[30rem] lg:w-[26rem]"></span>
        <span class="bottom-24 -left-16 size-80 bg-[#BAE6FD] opacity-40 lg:left-48 lg:size-[28rem]"></span>
        <span class="right-0 bottom-10 size-72 bg-[#FED7AA] opacity-25 lg:size-96"></span>
    </div>

    {{-- Desktop sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col justify-between border-r border-white/60 bg-white/55 p-5 backdrop-blur-xl lg:flex">
        <div class="flex flex-col gap-6">
            <a href="{{ Dashboard::getUrl() }}" class="flex items-center gap-3 px-2">
                <span class="flex size-9 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm">
                    <x-filament::icon :icon="Heroicon::OutlinedBuildingLibrary" class="size-5" />
                </span>
                <span class="flex flex-col">
                    <span class="font-display text-lg leading-none font-bold tracking-tight text-slate-900">SpendFlow</span>
                    <span class="mt-1 text-[11px] font-medium tracking-wider text-slate-500 uppercase">Request for Payment</span>
                </span>
            </a>

            <a href="{{ CreateRfp::getUrl() }}" @if ($onCreate) aria-current="page" @endif @class(['flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-[0_4px_14px_rgba(79,70,229,0.25)] transition hover:bg-indigo-500', 'ring-4 ring-indigo-500/20' => $onCreate])>
                <x-filament::icon :icon="Heroicon::Plus" class="size-4" />
                New RFP
            </a>

            <nav class="flex flex-col gap-1">
                @foreach ($navigation as $item)
                    <a
                        href="{{ $item['url'] }}"
                        @if ($item['active'] ?? false) aria-current="page" @endif
                        @class([
                            'flex items-center justify-between rounded-xl px-3.5 py-2.5 text-sm font-medium transition',
                            'bg-slate-900 text-white shadow-sm' => $item['active'] ?? false,
                            'text-slate-600 hover:bg-white/70 hover:text-slate-900' => ! ($item['active'] ?? false),
                        ])
                    >
                        <span class="flex items-center gap-3">
                            <x-filament::icon :icon="$item['icon']" class="size-5" />
                            {{ $item['label'] }}
                        </span>

                        @isset($item['badge'])
                            <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-indigo-600 px-1.5 text-[11px] font-bold text-white">{{ $item['badge'] }}</span>
                        @endisset
                    </a>
                @endforeach
            </nav>
        </div>

        <div class="flex items-center gap-3 rounded-xl bg-white/60 p-3">
            <img src="{{ filament()->getUserAvatarUrl($user) }}" alt="" class="size-9 rounded-full object-cover" />
            <div class="flex min-w-0 flex-1 flex-col">
                <span class="truncate text-sm font-semibold text-slate-900">{{ $user->name }}</span>
                <span class="truncate text-xs text-slate-500">{{ $user->email }}</span>
            </div>
            <form method="POST" action="{{ filament()->getLogoutUrl() }}">
                @csrf
                <button type="submit" title="Sign out" class="flex size-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-white hover:text-slate-900">
                    <x-filament::icon :icon="Heroicon::OutlinedArrowRightOnRectangle" class="size-5" />
                    <span class="sr-only">Sign out</span>
                </button>
            </form>
        </div>
    </aside>

    {{-- Content: full width on phones, a centered column on tablets, two columns beside the sidebar on laptops --}}
    <div class="relative lg:pl-64">
        <main id="fi-main-content" class="mx-auto w-full max-w-xl px-4 pt-4 pb-36 lg:max-w-[1120px] lg:px-8 lg:pt-8 lg:pb-12">
            {{ $slot }}
        </main>
    </div>

    {{--
        Mobile / tablet floating nav: New RFP raised in the middle; the chat bubble sits at
        the right end of the same row (placed by the theme). The New RFP flow has its own bottom bar.
    --}}
    @unless ($onCreate)
    @php
        $mobileNav = array_values($navigation);
        array_splice($mobileNav, intdiv(count($mobileNav), 2), 0, [['create' => true]]);
    @endphp
    <div class="pointer-events-none fixed inset-x-0 bottom-0 z-40 flex px-4 pe-[5.25rem] pb-[max(1.5rem,env(safe-area-inset-bottom))] lg:hidden">
        <nav class="pointer-events-auto flex h-14 flex-1 items-center justify-between rounded-full border border-white/10 bg-slate-900/90 px-2 shadow-2xl backdrop-blur-2xl">
            @foreach ($mobileNav as $item)
                @if ($item['create'] ?? false)
                    <a
                        href="{{ CreateRfp::getUrl() }}"
                        aria-label="New RFP"
                        class="-mt-7 flex size-14 shrink-0 items-center justify-center rounded-full bg-gradient-to-b from-slate-700 to-slate-950 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.25),0_12px_24px_-6px_rgba(15,23,42,0.6)] ring-[5px] ring-[#f5f6fb] transition active:scale-95"
                    >
                        <x-filament::icon :icon="Heroicon::Plus" class="size-7" />
                    </a>
                @else
                    <a
                        href="{{ $item['url'] }}"
                        aria-label="{{ $item['label'] }}"
                        @if ($item['active'] ?? false) aria-current="page" @endif
                        @class([
                            'relative flex size-10 shrink-0 items-center justify-center rounded-full transition',
                            'bg-white text-slate-900 shadow-sm' => $item['active'] ?? false,
                            'text-slate-400 hover:text-white' => ! ($item['active'] ?? false),
                        ])
                    >
                        <x-filament::icon :icon="$item['icon']" class="size-5" />

                        @isset($item['badge'])
                            <span class="absolute top-0.5 right-0.5 flex h-[15px] min-w-[15px] items-center justify-center rounded-full bg-rose-500 px-1 text-[9px] leading-none font-bold text-white ring-2 ring-slate-900">{{ $item['badge'] }}</span>
                        @endisset
                    </a>
                @endif
            @endforeach
        </nav>
    </div>
    @endunless

    {{-- Chat bubble, shared with the Hub: real conversations, not sample data --}}
    @unless ($onCreate)
        @livewire(CommentsChat::class, key('comments-chat-inbox'))
    @endunless

    @include('filament.my.notifications')
</x-filament-panels::layout.base>
