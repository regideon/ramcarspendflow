@php
    use App\Filament\Resources\P2p\Approvals\ApprovalResource;
    use App\Support\Peso;

    $lines = [
        ['Vatable sales', $tax['vatable_sales'], false],
        ['VAT-exempt', $tax['vat_exempt'], false],
        ['Zero-rated', $tax['vat_zero_rated'], false],
        ['VAT (12%)', $tax['vat'], false],
        ['EWT withheld', $tax['ewt'], true],
    ];
@endphp

<section data-tour="tax-summary" class="sf-card p-6">
    <x-sf.card-header heading="Tax summary" description="VAT and withholding tax (EWT) on invoices you processed this week">
        <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ now()->startOfWeek()->format('M j') }} – {{ now()->format('M j') }}</span>
    </x-sf.card-header>

    <table class="mt-3 w-full text-xs">
        <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-white/5 dark:text-gray-300">
            @foreach ($lines as [$label, $amount, $isDeduction])
                <tr>
                    <td @class(['py-2.5', 'font-medium text-gray-900 dark:text-white' => ! $isDeduction, 'font-medium text-primary-700 dark:text-primary-300' => $isDeduction])>{{ $label }}</td>
                    <td @class(['py-2.5 text-end font-mono tabular-nums', 'font-semibold text-primary-700 dark:text-primary-300' => $isDeduction])>
                        {{ $isDeduction ? '−'.Peso::format($amount) : Peso::format($amount) }}
                    </td>
                </tr>
            @endforeach
            <tr class="border-t-2 border-gray-200 bg-gray-50/80 font-bold text-gray-950 dark:border-white/10 dark:bg-white/5 dark:text-white">
                <td class="px-1 py-2.5 font-display">Net payable</td>
                <td class="py-2.5 pe-1 text-end font-mono text-sm tabular-nums">{{ Peso::format($tax['net']) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($tax['missing_wht'])
        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-warning-200/70 bg-warning-50 px-3 py-2 text-xs text-gray-700 dark:border-warning-500/20 dark:bg-warning-500/10 dark:text-gray-300">
            <div class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="size-4 shrink-0 text-warning-600 dark:text-warning-400" />
                <span><strong>{{ $tax['missing_wht'] }} P2P {{ str('invoice')->plural($tax['missing_wht']) }}</strong> still missing a withholding tax code.</span>
            </div>
            <a href="{{ ApprovalResource::getUrl('index') }}" class="font-semibold text-primary-600 hover:text-primary-800 dark:text-primary-400">Resolve</a>
        </div>
    @endif
</section>
