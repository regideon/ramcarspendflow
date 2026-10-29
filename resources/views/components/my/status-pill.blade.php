@props([
    'status',
])

@php
    use App\Enums\RfpStatus;

    $tone = match ($status) {
        RfpStatus::Draft => 'border-slate-200/70 bg-slate-100 text-slate-600',
        RfpStatus::ForRevision => 'border-orange-200 bg-orange-50 text-orange-700',
        RfpStatus::ForTreasury => 'border-amber-200 bg-amber-50 text-amber-700',
        RfpStatus::Completed => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        RfpStatus::Rejected, RfpStatus::Cancelled => 'border-rose-200 bg-rose-50 text-rose-700',
        default => 'border-indigo-100 bg-indigo-50 text-indigo-700',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap', $tone]) }}>
    {{ $status->getLabel() }}
</span>
