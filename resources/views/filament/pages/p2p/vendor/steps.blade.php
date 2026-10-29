{{--
    The vendor's invoicing flow: 1 Purchase Orders → 2 Goods Receipts → 3 Attach invoice & submit.
    $current: 0 on the Invoices list (nothing started), else the step the page is.
--}}
@php
    $steps = [
        1 => ['Purchase Orders', 'Review & select orders'],
        2 => ['Goods Receipts', 'Pick the received lines'],
        3 => ['Attach invoice & submit', 'Upload, AI check & send to AP'],
    ];
@endphp

<div data-tour="steps" class="sf-card flex flex-col items-start justify-between gap-4 p-4 lg:flex-row lg:items-center">
    <ol class="flex flex-wrap items-center gap-3 sm:gap-5">
        @foreach ($steps as $number => [$label, $hint])
            @php $state = $current === 0 ? 'todo' : ($number < $current ? 'done' : ($number === $current ? 'current' : 'todo')); @endphp
            @if (! $loop->first)
                <li @class(['hidden h-0.5 w-8 rounded-full sm:block', 'bg-success-400' => $state !== 'todo' || $current > $number, 'bg-gray-200 dark:bg-white/10' => $state === 'todo'])></li>
            @endif
            <li @class(['flex items-center gap-2.5', 'opacity-60' => $state === 'todo' && $current !== 0])>
                <span @class([
                    'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                    'bg-success-600 text-white' => $state === 'done',
                    'bg-primary-600 text-white shadow-sm ring-4 ring-primary-500/20' => $state === 'current',
                    'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400' => $state === 'todo',
                ])>
                    @if ($state === 'done')
                        <x-filament::icon icon="heroicon-m-check" class="size-4" />
                    @else
                        {{ $number }}
                    @endif
                </span>
                <span class="flex flex-col leading-tight">
                    <span @class([
                        'text-sm font-semibold',
                        'text-success-700 dark:text-success-400' => $state === 'done',
                        'text-primary-700 dark:text-primary-300' => $state === 'current',
                        'text-gray-700 dark:text-gray-300' => $state === 'todo',
                    ])>{{ $number }}. {{ $label }}</span>
                    <span class="text-[0.6875rem] text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                </span>
            </li>
        @endforeach
    </ol>

    @if ($current === 0 && ($processUrl ?? null))
        <a href="{{ $processUrl }}" class="inline-flex items-center gap-2 rounded-xl bg-gray-50 px-3.5 py-2 text-xs text-gray-600 ring-1 ring-gray-200 transition hover:bg-gray-100 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
            <x-filament::icon icon="heroicon-m-check-badge" class="size-4 text-primary-600" />
            Ready to bill Ramcar?
            <span class="rounded-lg bg-white px-2 py-0.5 font-bold text-gray-900 shadow-sm dark:bg-gray-800 dark:text-white">Start a new invoice: Process</span>
        </a>
    @endif
</div>
