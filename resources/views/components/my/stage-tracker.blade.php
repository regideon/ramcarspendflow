@props([
    'status',
    'note' => null,
    'showStage' => true,
])

@php
    use App\Enums\RfpStatus;
    use App\Filament\My\Pages\MyRequests;

    $stage = MyRequests::stageOf($status);
    $isDone = $status === RfpStatus::Completed;
@endphp

<div {{ $attributes }}>
    <div class="grid h-1.5 w-full grid-cols-4 gap-1.5">
        @foreach (MyRequests::STAGES as $index => $name)
            <span
                title="{{ $name }}"
                @class([
                    'rounded-full',
                    'bg-indigo-600' => $index < $stage,
                    'animate-pulse' => ($index === $stage - 1) && ! $isDone,
                    'bg-slate-200/80' => $index >= $stage,
                ])
            ></span>
        @endforeach
    </div>

    @if ($showStage || filled($note))
    <div class="mt-1.5 flex items-center justify-between gap-2 text-[10px]">
        @if ($showStage)
            <span class="shrink-0 font-medium text-slate-400">
                {{ $isDone ? 'All stages done' : 'Stage '.$stage.' · '.MyRequests::STAGES[$stage - 1] }}
            </span>
        @endif

        @if (filled($note))
            <span @class([
                'flex min-w-0 items-center gap-1 truncate font-medium',
                'text-amber-700' => $status === RfpStatus::ForTreasury,
                'text-slate-500' => $status !== RfpStatus::ForTreasury,
            ])>
                <span @class(['size-1.5 shrink-0 rounded-full', 'bg-amber-500' => $status === RfpStatus::ForTreasury, 'bg-indigo-500' => $status !== RfpStatus::ForTreasury])></span>
                <span class="truncate">{{ $note }}</span>
            </span>
        @endif
    </div>
    @endif
</div>
