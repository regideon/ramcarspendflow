@php
    // Same totals as the old screen's row under each goods receipt
    $number = fn ($value): float => (float) preg_replace('/[^0-9.\-]/', '', (string) ($value ?? 0));
    $poQty = collect($items)->sum(fn (array $item): float => (float) ($item['quantity_po'] ?? 0));
    $served = collect($items)->sum(fn (array $item): float => (float) ($item['quantity_served'] ?? 0));
    $total = collect($items)->sum(fn (array $item): float => $number($item['amount'] ?? 0));
@endphp

<div class="flex flex-wrap items-center justify-end gap-x-6 gap-y-1 rounded-xl bg-gray-50 px-4 py-3 text-sm dark:bg-white/5">
    <span class="text-gray-500 dark:text-gray-400">Total PO Qty: <strong class="font-mono text-gray-900 dark:text-white">{{ number_format($poQty) }}</strong></span>
    <span class="text-gray-500 dark:text-gray-400">Total Invoice Qty: <strong class="font-mono text-gray-900 dark:text-white">{{ number_format($served) }}</strong></span>
    <span class="text-gray-500 dark:text-gray-400">Subtotal GR {{ $gr }}: <strong class="font-mono text-base font-bold text-gray-900 dark:text-white">{{ $currency }} {{ number_format($total, 2) }}</strong></span>
</div>
