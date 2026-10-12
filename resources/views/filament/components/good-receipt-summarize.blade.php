@php
    $items = $items ?? [];
    $hidePricing = auth()->user()?->hasRole(\App\Enums\RoleName::Vendor) ?? false;

    $totalQuantityPo = collect($items)->sum(fn (array $item): float => (float) ($item['quantity_po'] ?? 0));
    $totalGr = collect($items)->sum(fn (array $item): float => (float) ($item['quantity_served'] ?? 0));
    $totalInvoiceQuantity = collect($items)->sum(fn (array $item): float => (float) ($item['quantity'] ?? 0));
    $totalAmount = collect($items)->sum(
        fn (array $item): float => (float) ($item['quantity'] ?? 0) * (float) ($item['unit_cost'] ?? 0),
    );

    $fmt = fn (float $value): string => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
@endphp

{{-- Per-submission totals. Mirrors the legacy footer, with pricing withheld from vendors. --}}
<div style="display:flex; flex-wrap:wrap; justify-content:flex-end; gap:12px 32px; align-items:center; font-weight:600; border-top:2px solid #e5e7eb; padding:12px 16px; margin-top:8px;">
    <span>Total PO Qty: <span>{{ $fmt($totalQuantityPo) }}</span></span>
    <span>Total GR Qty: <span>{{ $fmt($totalGr) }}</span></span>
    <span>Total Invoice Qty: <span>{{ $fmt($totalInvoiceQuantity) }}</span></span>

    @unless ($hidePricing)
        <span>Total Amount: <span>₱{{ number_format($totalAmount, 2) }}</span></span>
    @endunless
</div>
