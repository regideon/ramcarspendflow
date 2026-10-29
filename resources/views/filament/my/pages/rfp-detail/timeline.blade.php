@php
    use Filament\Support\Icons\Heroicon;

    $stateLabels = [
        'done' => ['Done', 'text-emerald-700'],
        'current' => ['In progress', 'text-indigo-700'],
        'returned' => ['Returned', 'text-orange-700'],
        'rejected' => ['Rejected', 'text-rose-700'],
        'upcoming' => ['Upcoming', 'text-slate-400'],
    ];
@endphp

<section class="my-glass rounded-[20px] p-4 lg:p-6">
    <div class="flex items-center justify-between gap-2">
        <h2 class="font-display text-base font-bold text-slate-900 lg:text-lg">Where it is now</h2>
        <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-[11px] font-semibold text-indigo-700">{{ $stageChip }}</span>
    </div>

    <ol class="relative mt-4 flex flex-col gap-4">
        <span class="absolute top-2 bottom-2 left-[9px] w-0.5 bg-slate-200/80" aria-hidden="true"></span>

        @foreach ($timeline as $stage)
            <li @class(['relative pl-8', 'opacity-60' => $stage['state'] === 'upcoming'])>
                <span @class([
                    'absolute top-0 left-0 flex size-5 items-center justify-center rounded-full ring-4 ring-white/70',
                    'bg-emerald-600 text-white' => $stage['state'] === 'done',
                    'bg-indigo-600 text-white shadow-[0_0_12px_rgba(79,70,229,0.5)]' => $stage['state'] === 'current',
                    'bg-orange-500 text-white' => $stage['state'] === 'returned',
                    'bg-rose-500 text-white' => $stage['state'] === 'rejected',
                    'bg-slate-200 text-slate-400' => $stage['state'] === 'upcoming',
                ])>
                    @switch($stage['state'])
                        @case('done')
                            <x-filament::icon :icon="Heroicon::Check" class="size-3" />
                            @break
                        @case('current')
                            <span class="size-2 animate-pulse rounded-full bg-white"></span>
                            @break
                        @case('returned')
                            <x-filament::icon :icon="Heroicon::ArrowUturnLeft" class="size-3" />
                            @break
                        @case('rejected')
                            <x-filament::icon :icon="Heroicon::XMark" class="size-3" />
                            @break
                        @default
                            <span class="size-1.5 rounded-full bg-slate-400"></span>
                    @endswitch
                </span>

                <div class="flex items-baseline justify-between gap-2">
                    <span @class(['text-sm font-semibold', 'text-indigo-700' => $stage['state'] === 'current', 'text-slate-900' => $stage['state'] !== 'current'])>{{ $stage['name'] }}</span>
                    <span class="shrink-0 text-[10px] font-semibold tracking-wide uppercase {{ $stateLabels[$stage['state']][1] }}">{{ $stateLabels[$stage['state']][0] }}</span>
                </div>

                @if (filled($stage['note']))
                    <p class="mt-0.5 text-xs text-slate-500">{{ $stage['note'] }}</p>
                @endif

                @if ($stage['approvers'])
                    <ul class="mt-2 flex flex-col gap-1.5">
                        @foreach ($stage['approvers'] as $approver)
                            <li @class([
                                'flex flex-wrap items-center justify-between gap-x-2 gap-y-1 rounded-xl p-2.5',
                                'bg-indigo-50/80 shadow-sm ring-1 ring-indigo-100' => $approver['state'] === 'current',
                                'bg-white/60' => in_array($approver['state'], ['done', 'rejected'], true),
                                'opacity-70' => $approver['state'] === 'upcoming',
                            ])>
                                <span class="flex min-w-0 items-center gap-2">
                                    @switch($approver['state'])
                                        @case('done')
                                            <x-filament::icon :icon="Heroicon::CheckCircle" class="size-4 shrink-0 text-emerald-600" />
                                            @break
                                        @case('current')
                                            <span class="ml-1 size-2 shrink-0 animate-pulse rounded-full bg-indigo-600"></span>
                                            @break
                                        @case('rejected')
                                            <x-filament::icon :icon="Heroicon::XCircle" class="size-4 shrink-0 text-rose-500" />
                                            @break
                                        @default
                                            <span class="ml-0.5 size-3 shrink-0 rounded-full border-2 border-slate-300"></span>
                                    @endswitch
                                    <span @class(['text-xs', 'font-bold text-slate-900' => $approver['state'] === 'current', 'font-medium text-slate-700' => $approver['state'] !== 'current'])>{{ $approver['role'] }}</span>
                                    @if ($approver['isMe'])
                                        <span class="shrink-0 rounded-full bg-indigo-600 px-1.5 text-[10px] font-semibold text-white">You</span>
                                    @endif
                                </span>

                                @if (filled($approver['note']))
                                    <span @class([
                                        'text-[10px] font-medium',
                                        'shrink-0 rounded-full bg-amber-50 px-2 py-0.5 text-amber-700' => $approver['state'] === 'current',
                                        'basis-full pl-6 leading-snug text-rose-700' => $approver['state'] === 'rejected',
                                        'shrink-0 text-slate-400' => $approver['state'] === 'done',
                                    ])>{{ $approver['note'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @endforeach
    </ol>
</section>
