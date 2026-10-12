@php
    use App\Filament\Resources\P2p\Approvals\ApprovalResource;
    use App\Support\Peso;

    $lines = [
        ['Vatable sales', $tax['vatable_sales'], false],
        ['VAT-exempt', $tax['vat_exempt'], false],
        ['Zero-rated', $tax['vat_zero_rated'], false],
        ['VAT', $tax['vat'], false],
        ['EWT', -$tax['ewt'], true],
    ];
@endphp

<x-filament-widgets::widget class="h-full">
    <x-filament::section heading="Tax summary" description="Invoices I processed this week" class="h-full">
        <div class="overflow-hidden rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @foreach ($lines as [$label, $amount, $isDeduction])
                        <tr>
                            <td @class(['px-3 py-2', 'text-warning-700 dark:text-warning-400' => $isDeduction, 'text-gray-600 dark:text-gray-400' => ! $isDeduction])>{{ $label }}</td>
                            <td @class(['px-3 py-2 text-end font-medium tabular-nums', 'text-warning-700 dark:text-warning-400' => $isDeduction, 'text-gray-950 dark:text-white' => ! $isDeduction])>
                                {{ $isDeduction ? '−'.Peso::format(abs($amount)) : Peso::format($amount) }}
                            </td>
                        </tr>
                    @endforeach
                    <tr class="bg-gray-50 dark:bg-white/5">
                        <td class="px-3 py-2.5 font-semibold text-gray-950 dark:text-white">Net payable</td>
                        <td class="px-3 py-2.5 text-end font-bold tabular-nums text-primary-600 dark:text-primary-400">{{ Peso::format($tax['net']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if ($tax['missing_wht'])
            <div class="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-warning-50 px-3 py-2 text-sm text-gray-700 dark:bg-warning-500/10 dark:text-gray-300">
                <span class="flex items-center gap-2">
                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-4 w-4 shrink-0 text-warning-600 dark:text-warning-400" />
                    <span><strong>{{ $tax['missing_wht'] }} P2P {{ str('invoice')->plural($tax['missing_wht']) }}</strong> still missing a withholding tax code.</span>
                </span>
                <x-filament::link :href="ApprovalResource::getUrl('index')" size="sm">Resolve</x-filament::link>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
