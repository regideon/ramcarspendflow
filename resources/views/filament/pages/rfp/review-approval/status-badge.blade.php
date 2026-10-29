@php
    $color = $status?->getColor() ?? 'gray';
@endphp

<span @class([
    'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold',
    'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => $color === 'gray',
    'bg-warning-100 text-warning-800 dark:bg-warning-500/15 dark:text-warning-300' => $color === 'warning',
    'bg-success-100 text-success-800 dark:bg-success-500/15 dark:text-success-300' => $color === 'success',
    'bg-danger-100 text-danger-700 dark:bg-danger-500/15 dark:text-danger-300' => $color === 'danger',
    'bg-primary-50 text-primary-800 ring-1 ring-primary-200/60 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/30' => ! in_array($color, ['gray', 'warning', 'success', 'danger'], true),
])>
    <span @class([
        'size-1.5 rounded-full',
        'bg-gray-400' => $color === 'gray',
        'bg-warning-500' => $color === 'warning',
        'bg-success-500' => $color === 'success',
        'bg-danger-500' => $color === 'danger',
        'animate-pulse bg-primary-500' => ! in_array($color, ['gray', 'warning', 'success', 'danger'], true),
    ])></span>
    {{ $status?->getLabel() ?? '—' }}
</span>
