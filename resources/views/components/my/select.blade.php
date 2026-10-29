@props(['options', 'placeholder' => 'Select…'])

{{-- A glass select. `options` is a list (value = label) or a value => label map. --}}
<span class="relative block">
    <select {{ $attributes->class('w-full appearance-none rounded-xl border border-white/90 bg-white/80 py-2.5 pr-9 pl-3.5 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:bg-white focus:ring-3 focus:ring-indigo-500/15 focus:outline-none') }}>
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $value => $label)
            <option value="{{ array_is_list($options) ? $label : $value }}">{{ $label }}</option>
        @endforeach
    </select>
    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::ChevronDown" class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-slate-400" />
</span>
