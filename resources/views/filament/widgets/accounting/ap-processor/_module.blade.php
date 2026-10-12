{{-- RFP / P2P chip, used on every queue item. --}}
<span @class([
    'inline-flex shrink-0 items-center rounded px-1.5 py-0.5 text-[11px] font-bold tracking-wide',
    'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300' => $module === 'RFP',
    'bg-teal-50 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300' => $module === 'P2P',
])>{{ $module }}</span>
