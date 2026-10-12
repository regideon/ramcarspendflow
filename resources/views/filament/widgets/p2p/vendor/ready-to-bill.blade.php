@php
    use App\Filament\Resources\P2p\GoodsReceipts\GoodsReceiptResource;

    // "Bill by" chip: overdue red, ≤3 days red, otherwise gray.
    $billBy = function (Illuminate\Support\Carbon $date): array {
        $days = (int) today()->diffInDays($date, false);

        return match (true) {
            $days < 0 => ['danger', abs($days).'d overdue'],
            $days <= 3 => ['danger', $days.'d left'],
            default => ['gray', $days.'d left'],
        };
    };
@endphp

<x-filament-widgets::widget class="h-full">
    <x-filament::section
        heading="Goods receipts ready to bill"
        description="Delivered and received by Ramcar. Select receipts to bill together."
        class="h-full"
        x-data="{ selected: [] }"
    >
        <x-slot name="afterHeader">
            <x-filament::badge color="gray">{{ count($receipts) }} receipts</x-filament::badge>
        </x-slot>

        <div class="-mx-4 overflow-x-auto sm:-mx-6">
            <table class="w-full min-w-[44rem] divide-y divide-gray-200 text-sm dark:divide-white/5">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                        <th class="w-10 px-4 py-3 sm:ps-6"><span class="sr-only">Select</span></th>
                        <th class="px-3 py-3 text-start">GR no.</th>
                        <th class="px-3 py-3 text-start">PO · Buyer</th>
                        <th class="px-3 py-3 text-start">Material</th>
                        <th class="px-3 py-3 text-end">Qty received</th>
                        <th class="px-4 py-3 text-end sm:pe-6">Bill by</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @foreach ($receipts as $receipt)
                        @php([$chipColor, $chipLabel] = $billBy($receipt['bill_by']))
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5" x-bind:class="selected.includes(@js($receipt['gr'])) && 'bg-primary-50/60 dark:bg-primary-500/5'">
                            <td class="px-4 py-3 sm:ps-6">
                                <input type="checkbox" value="{{ $receipt['gr'] }}" x-model="selected" class="fi-checkbox-input" aria-label="Select GR {{ $receipt['gr'] }}" />
                            </td>
                            <td class="whitespace-nowrap px-3 py-3">
                                <div class="font-semibold text-gray-950 dark:text-white">{{ $receipt['gr'] }}</div>
                                <div @class(['text-xs', 'font-medium text-primary-600 dark:text-primary-400' => $receipt['partial'], 'text-gray-500' => ! $receipt['partial']])>
                                    {{ $receipt['partial'] ? 'Partial receipt' : 'GR '.$receipt['gr_date']->format('M j') }}
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3">
                                <div class="text-gray-950 dark:text-white">{{ $receipt['po'] }} / {{ $receipt['po_item'] }}</div>
                                <div class="text-xs text-gray-500">{{ $receipt['company'] }}</div>
                            </td>
                            <td class="max-w-60 px-3 py-3">
                                <div class="truncate text-gray-950 dark:text-white" title="{{ $receipt['material'] }}">{{ $receipt['material'] }}</div>
                                <div class="text-xs text-gray-500">{{ $receipt['material_code'] }}</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-end tabular-nums text-gray-950 dark:text-white">
                                {{ number_format($receipt['quantity']) }} {{ $receipt['uom'] }}
                            </td>
                            <td class="px-4 py-3 text-end sm:pe-6">
                                <x-filament::badge :color="$chipColor" size="sm" class="inline-flex">{{ $chipLabel }}</x-filament::badge>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div
            x-show="selected.length"
            x-cloak
            class="-mx-4 -mb-4 mt-4 flex items-center justify-between gap-3 bg-gray-900 px-4 py-3 text-white sm:-mx-6 sm:-mb-6 sm:px-6 dark:bg-white/10"
        >
            <p class="text-sm">
                <span class="font-semibold" x-text="selected.length"></span>
                <span x-text="selected.length === 1 ? 'receipt selected' : 'receipts selected'"></span>
                <span class="text-gray-400">· billed together on one invoice submission</span>
            </p>
            <x-filament::button tag="a" :href="GoodsReceiptResource::getUrl('index')" size="sm" icon="heroicon-m-arrow-right" icon-position="after">
                Bill selected
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
