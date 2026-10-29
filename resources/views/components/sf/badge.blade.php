@props(['color' => 'gray', 'dot' => true])

{{-- Status pill in a Filament color (success, warning, danger, info/primary, gray). --}}
<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold',
    'bg-success-100 text-success-800 dark:bg-success-500/15 dark:text-success-300' => $color === 'success',
    'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300' => $color === 'warning',
    'bg-danger-100 text-danger-700 dark:bg-danger-500/15 dark:text-danger-300' => $color === 'danger',
    'bg-primary-100 text-primary-800 dark:bg-primary-500/15 dark:text-primary-300' => in_array($color, ['info', 'primary'], true),
    'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => ! in_array($color, ['success', 'warning', 'danger', 'info', 'primary'], true),
]) }}>
    @if ($dot)
        <span @class([
            'size-1.5 rounded-full',
            'bg-success-500' => $color === 'success',
            'bg-warning-500' => $color === 'warning',
            'bg-danger-500' => $color === 'danger',
            'bg-primary-500' => in_array($color, ['info', 'primary'], true),
            'bg-gray-400' => ! in_array($color, ['success', 'warning', 'danger', 'info', 'primary'], true),
        ])></span>
    @endif
    {{ $slot }}
</span>
