@props(['heading', 'description' => null])

{{-- Card title row of the redesigned pages: heading + description, anything in the slot on the right. --}}
<div {{ $attributes->class('flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 pb-3 dark:border-white/5') }}>
    <div>
        <h3 class="font-display text-sm font-bold text-gray-950 dark:text-white">{{ $heading }}</h3>
        @if ($description)
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $description }}</p>
        @endif
    </div>
    {{ $slot }}
</div>
