@php
    /**
     * The tax summary, live from the Tax section (same values as the old screen's Summary).
     *
     * @var array<string, mixed> $data
     */
    $amount = fn (string $key): float => (float) str_replace(',', '', (string) ($data[$key] ?? 0));
    $money = fn (string $key): string => $currency.number_format($amount($key), 2);

    $rows = [
        ['Vatable Sales', 'vatable_sales'],
        ['VAT Exempt', 'vat_exempt'],
        ['VAT Zero Rated', 'vat_zero_rated'],
        ['VAT', 'vat'],
    ];
@endphp

<section class="sf-card flex h-full flex-col justify-between p-6">
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h2 class="font-display text-lg font-bold text-gray-900 dark:text-white">Summary</h2>
            <span class="text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">PHP</span>
        </div>

        <dl class="space-y-3 text-sm">
            @foreach ($rows as [$label, $key])
                <div class="flex items-center justify-between text-gray-500 dark:text-gray-400">
                    <dt>{{ $label }}</dt>
                    <dd class="font-mono font-medium text-gray-900 dark:text-white">{{ $money($key) }}</dd>
                </div>
            @endforeach
            <div class="flex items-center justify-between border-t border-gray-100 pt-2 font-semibold text-gray-900 dark:border-white/10 dark:text-white">
                <dt>Total</dt>
                <dd class="font-mono">{{ $money('total') }}</dd>
            </div>
            <div class="flex items-center justify-between text-gray-500 dark:text-gray-400">
                <dt>EWT</dt>
                <dd class="font-mono font-medium">{{ $amount('ewt') > 0 ? '-' : '' }}{{ $money('ewt') }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-6 border-t-2 border-gray-100 pt-4 dark:border-white/10">
        <div class="flex items-baseline justify-between gap-3">
            <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Net payable</span>
            <span class="font-display text-3xl font-bold tabular-nums tracking-tight text-gray-900 dark:text-white">{{ $money('net') }}</span>
        </div>
    </div>
</section>
