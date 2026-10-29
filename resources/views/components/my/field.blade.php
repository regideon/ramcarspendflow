@props(['label', 'error' => null, 'hint' => null])

{{-- A labelled form field for the My panel; shows the validation message for `error` --}}
<div {{ $attributes->class('flex min-w-0 flex-col gap-1.5') }}>
    <span class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">{{ $label }}</span>

    {{ $slot }}

    @if (filled($error))
        @error($error)
            <span class="text-xs font-medium text-rose-600">{{ $message }}</span>
        @enderror
    @endif

    @if (filled($hint))
        <span class="text-[11px] text-slate-400">{{ $hint }}</span>
    @endif
</div>
