{{--
    Step 4–6 tax entries (legacy "Process Tax Codes"). Everything while editing runs in
    Alpine: the lines are entangled without .live, so choosing a code or typing a gross
    never calls the server or re-renders the other rows. The state goes up with the
    Save button, and RfpTaxProcessing recomputes and validates it there.
--}}
@php
    $statePath = $getStatePath();
@endphp

<div
    wire:ignore
    x-data="{
        lines: $wire.$entangle(@js($statePath)),
        taxRates: @js($taxRates),
        withholdingRates: @js($withholdingRates),
        lockInputTax: @js($lockInputTax),
        round(value) {
            return Math.round((Number(value) + Number.EPSILON) * 100) / 100
        },
        money(value) {
            return (Number(value) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        },
        gross(line) {
            return this.round(parseFloat(line.amount) || 0)
        },
        computed(line) {
            const gross = this.gross(line)
            const taxRate = this.taxRates[line.tax_code_id]

            if (taxRate === undefined) {
                return null
            }

            const netOfVat = this.round(gross / (1 + taxRate / 100))
            const withholdingRate = this.withholdingRates[line.withholding_tax_code_id] ?? 0
            const withholding = this.round(netOfVat * withholdingRate / 100)

            return {
                vat: this.round(gross - netOfVat),
                netOfVat: netOfVat,
                withholding: withholding,
                netOfWithholding: this.round(gross - withholding),
            }
        },
        isMissing(line) {
            return ! line.tax_code_id || ! line.withholding_tax_code_id
        },
        get missingCount() {
            return this.lines.filter((line) => this.isMissing(line)).length
        },
        get totals() {
            const byCurrency = {}

            this.lines.forEach((line) => {
                const total = byCurrency[line.currency] ??= { currency: line.currency, submitted: 0, current: 0, netOfWithholding: 0 }
                total.submitted += parseFloat(line.original_amount) || 0
                total.current += this.gross(line)
                total.netOfWithholding += this.computed(line)?.netOfWithholding ?? 0
            })

            return Object.values(byCurrency).map((total) => ({ ...total, variance: this.round(total.current - total.submitted) }))
        },
    }"
    class="grid gap-4"
>
    <div class="overflow-x-auto rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
        <table class="w-full min-w-[86rem] text-sm">
            <thead class="bg-gray-50 text-xs font-semibold text-gray-600 dark:bg-white/5 dark:text-gray-300">
                <tr>
                    <th class="sticky start-0 z-10 w-38 bg-gray-50 px-3 py-2.5 text-start dark:bg-gray-900">Invoice</th>
                    <th class="w-50 px-2 py-2.5 text-start">Gross</th>
                    <th class="w-64 px-2 py-2.5 text-start">Input tax</th>
                    <th class="w-72 px-2 py-2.5 text-start">Withholding tax</th>
                    <th class="w-28 px-2 py-2.5 text-start">WHT type</th>
                    <th class="w-28 px-2 py-2.5 text-end">VAT</th>
                    <th class="w-32 px-2 py-2.5 text-end">Net of VAT</th>
                    <th class="w-28 px-2 py-2.5 text-end">Withholding</th>
                    <th class="w-32 px-3 py-2.5 text-end">Net of WHT</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                <template x-for="(line, index) in lines" :key="line.id">
                    <tr class="align-top">
                        <td class="sticky start-0 z-10 bg-white px-3 py-2 dark:bg-gray-900">
                            <div class="font-medium text-gray-950 dark:text-white" x-text="line.invoice_number || '—'"></div>
                            <div class="max-w-34 truncate text-xs text-gray-500 dark:text-gray-400" x-text="line.description" :title="line.description"></div>
                        </td>
                        <td class="px-2 py-2">
                            <x-filament::input.wrapper>
                                <x-slot name="prefix"><span x-text="line.currency"></span></x-slot>
                                <x-filament::input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    class="text-end"
                                    x-model="line.amount"
                                    x-bind:readonly="! line.is_php"
                                    x-bind:title="line.is_php ? '' : 'Only PHP lines can be re-split'"
                                />
                            </x-filament::input.wrapper>
                        </td>
                        <td class="px-2 py-2">
                            <x-filament::input.wrapper x-bind:class="! line.tax_code_id && 'ring-danger-600/60 dark:ring-danger-500/60'">
                                <x-filament::input.select x-model="line.tax_code_id" x-bind:disabled="lockInputTax" aria-label="Input tax">
                                    <option value="">Select input tax</option>
                                    @foreach ($taxCodeOptions as $id => $label)
                                        <option value="{{ $id }}">{{ $label }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </td>
                        <td class="px-2 py-2">
                            <x-filament::input.wrapper x-bind:class="line.tax_code_id && ! line.withholding_tax_code_id && 'ring-danger-600/60 dark:ring-danger-500/60'">
                                <x-filament::input.select x-model="line.withholding_tax_code_id" x-bind:disabled="! line.tax_code_id" aria-label="Withholding tax">
                                    <option value="" x-text="line.tax_code_id ? 'Select withholding tax' : 'Input tax first'"></option>
                                    @foreach ($withholdingOptions as $id => $label)
                                        <option value="{{ $id }}">{{ $label }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </td>
                        <td class="px-2 py-2">
                            <x-filament::input.wrapper disabled>
                                <x-filament::input.select disabled aria-label="Withholding tax type">
                                    <option>{{ $withholdingTypeLabel }}</option>
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </td>
                        <td class="px-2 py-2 text-end tabular-nums text-gray-700 dark:text-gray-200" x-text="computed(line) ? money(computed(line).vat) : '—'"></td>
                        <td class="px-2 py-2 text-end tabular-nums text-gray-700 dark:text-gray-200" x-text="computed(line) ? money(computed(line).netOfVat) : '—'"></td>
                        <td class="px-2 py-2 text-end tabular-nums text-gray-700 dark:text-gray-200" x-text="computed(line) ? money(computed(line).withholding) : '—'"></td>
                        <td class="px-3 py-2 text-end font-semibold tabular-nums text-gray-950 dark:text-white" x-text="computed(line) ? money(computed(line).netOfWithholding) : '—'"></td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-[40rem] text-sm">
            <thead class="text-xs text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="pe-8 text-start font-medium">Currency</th>
                    <th class="pe-8 text-end font-medium">Submitted gross</th>
                    <th class="pe-8 text-end font-medium">Current gross</th>
                    <th class="pe-8 text-end font-medium">Variance (must be 0)</th>
                    <th class="text-end font-medium">Total net of WHT</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="total in totals" :key="total.currency">
                    <tr class="border-t border-gray-200 tabular-nums dark:border-white/10">
                        <td class="py-1.5 pe-8 font-medium" x-text="total.currency"></td>
                        <td class="py-1.5 pe-8 text-end" x-text="money(total.submitted)"></td>
                        <td class="py-1.5 pe-8 text-end" x-text="money(total.current)"></td>
                        <td class="py-1.5 pe-8 text-end" :class="total.variance === 0 ? 'text-success-600 dark:text-success-400' : 'font-semibold text-danger-600 dark:text-danger-400'" x-text="money(total.variance)"></td>
                        <td class="py-1.5 text-end font-semibold" x-text="money(total.netOfWithholding)"></td>
                    </tr>
                </template>
            </tbody>
        </table>
        <p x-show="missingCount > 0" x-cloak class="mt-2 text-sm font-medium text-danger-600 dark:text-danger-400">
            <span x-text="missingCount"></span> <span x-text="missingCount === 1 ? 'line needs' : 'lines need'"></span> an input tax and a withholding tax.
        </p>
    </div>
</div>
