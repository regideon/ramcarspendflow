@props(['module', 'long' => false])

{{-- RFP / P2P type chip: RFP indigo, P2P slate. `long` reads "P2P invoice". --}}
<span {{ $attributes->class([
    'inline-flex shrink-0 items-center rounded-full border px-2 py-0.5 text-[0.625rem] font-semibold',
    'border-primary-200/60 bg-primary-50 text-primary-700 dark:border-primary-500/30 dark:bg-primary-500/10 dark:text-primary-300' => $module === 'RFP',
    'border-gray-200 bg-gray-100 text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-300' => $module !== 'RFP',
]) }}>{{ $module === 'P2P' && $long ? 'P2P invoice' : $module }}</span>
