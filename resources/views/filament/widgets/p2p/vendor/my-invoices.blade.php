@php
    use App\Services\P2p\P2pVendorDashboard;
    use App\Support\Peso;

    $invoices = collect($invoices)->map(fn (array $invoice): array => [...$invoice, 'view' => P2pVendorDashboard::vendorView($invoice['status'])]);

    $tabs = ['all' => ['All', $invoices->count()]];
    foreach (P2pVendorDashboard::GROUPS as $key => $label) {
        $tabs[$key] = [$label, $invoices->where('view.group', $key)->count()];
    }

    // Days at this stage: 0–2d gray, 3–5d info, 6–10d warning, 11+d danger.
    $agingColor = fn (int $days): string => match (true) {
        $days <= 2 => 'gray',
        $days <= 5 => 'info',
        $days <= 10 => 'warning',
        default => 'danger',
    };
@endphp

<x-filament-widgets::widget>
    <x-filament::section heading="My invoices" description="From submission to payment, with the SAP document numbers" x-data="{ tab: 'all' }">
        <x-filament::tabs class="mb-4" contained>
            @foreach ($tabs as $key => [$label, $count])
                <x-filament::tabs.item :alpine-active="'tab === \'' . $key . '\''" x-on:click="tab = '{{ $key }}'" :badge="$count">
                    {{ $label }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        <div class="-mx-4 overflow-x-auto sm:-mx-6">
            <table class="w-full min-w-[68rem] divide-y divide-gray-200 text-sm dark:divide-white/5">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3 text-start sm:ps-6">GRC · Invoice</th>
                        <th class="px-3 py-3 text-start">PO · Buyer</th>
                        <th class="px-3 py-3 text-start">Invoice date</th>
                        <th class="px-3 py-3 text-end">Submitted</th>
                        <th class="px-3 py-3 text-end">Net payable</th>
                        <th class="px-3 py-3 text-center">3-way match</th>
                        <th class="px-3 py-3 text-center">Progress</th>
                        <th class="px-3 py-3 text-start">Status</th>
                        <th class="px-4 py-3 text-start sm:pe-6">SAP reference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @foreach ($invoices as $invoice)
                        @php
                            $view = $invoice['view'];
                            $stage = $view['stage'];
                            $isPaid = $view['group'] === 'paid';
                            $isClosed = $view['group'] === 'closed';
                        @endphp
                        <tr x-show="tab === 'all' || tab === '{{ $view['group'] }}'" @class(['hover:bg-gray-50 dark:hover:bg-white/5', 'bg-warning-50/40 dark:bg-warning-500/5' => $view['group'] === 'action'])>
                            <td class="whitespace-nowrap px-4 py-3 sm:ps-6">
                                <div class="font-semibold text-primary-600 dark:text-primary-400">{{ $invoice['grc'] }}</div>
                                <div class="text-xs text-gray-500">{{ $invoice['invoice_no'] }}</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3">
                                <div class="text-gray-950 dark:text-white">{{ $invoice['po'] }}</div>
                                <div class="text-xs text-gray-500">{{ $invoice['company'] }}</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-gray-500 dark:text-gray-400">{{ $invoice['invoice_date']->format('M j, Y') }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-end tabular-nums text-gray-700 dark:text-gray-300">{{ Peso::format($invoice['amount']) }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-end font-semibold tabular-nums text-gray-950 dark:text-white">
                                @if ($invoice['net'] !== null)
                                    {{ Peso::format($invoice['net']) }}
                                @else
                                    <span class="font-normal text-gray-400" title="Shown once Ramcar AP assigns the tax codes">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center">
                                <x-filament::badge :color="$invoice['match']->getColor()" size="sm" class="inline-flex" :tooltip="$invoice['match']->description()">
                                    {{ $invoice['match']->getLabel() }}
                                </x-filament::badge>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex items-center justify-center" title="Step {{ $stage }} of 7: {{ P2pVendorDashboard::STAGES[$stage] }}">
                                    @foreach (P2pVendorDashboard::STAGES as $number => $label)
                                        @php
                                            $state = match (true) {
                                                $isClosed => $number < $stage ? 'done' : ($number === $stage ? 'current' : 'upcoming'),
                                                $number < $stage, $isPaid => 'done',
                                                $number === $stage => 'current',
                                                default => 'upcoming',
                                            };
                                        @endphp
                                        @unless ($loop->first)
                                            <span @class(['h-0.5 w-3', 'bg-success-500' => $number <= $stage, 'bg-gray-200 dark:bg-white/10' => $number > $stage])></span>
                                        @endunless
                                        <span
                                            @class([
                                                'grid shrink-0 place-items-center rounded-full text-[9px] font-bold text-white',
                                                'h-3.5 w-3.5 bg-success-500' => $state === 'done',
                                                'h-4 w-4 ring-2' => $state === 'current',
                                                'bg-info-500 ring-info-200 dark:ring-info-500/30' => $state === 'current' && in_array($view['color'], ['info', 'primary'], true),
                                                'bg-warning-500 ring-warning-200 dark:ring-warning-500/30' => $state === 'current' && $view['color'] === 'warning',
                                                'bg-danger-500 ring-danger-200 dark:ring-danger-500/30' => $state === 'current' && $view['color'] === 'danger',
                                                'bg-gray-400 ring-gray-200 dark:ring-white/10' => $state === 'current' && $view['color'] === 'gray',
                                                'h-2.5 w-2.5 bg-gray-200 dark:bg-white/10' => $state === 'upcoming',
                                            ])
                                        >
                                            @if ($state === 'done')
                                                ✓
                                            @elseif ($state === 'current')
                                                {{ in_array($view['color'], ['warning', 'danger'], true) ? '!' : $number }}
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <x-filament::badge :color="$view['color']" class="inline-flex" :tooltip="$invoice['status']->getLabel()">{{ $view['label'] }}</x-filament::badge>
                                @if ($invoice['note'])
                                    <div class="mt-1 max-w-48 truncate text-xs text-gray-500" title="{{ $invoice['note'] }}">{{ $invoice['note'] }}</div>
                                @elseif (! $isPaid && ! $isClosed)
                                    <div class="mt-1"><x-filament::badge :color="$agingColor($invoice['days'])" size="sm" class="inline-flex">{{ $invoice['days'] }}d at this step</x-filament::badge></div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 sm:pe-6">
                                @if ($invoice['sap_ref'])
                                    <span class="inline-flex items-center gap-1 font-medium text-gray-950 dark:text-white">
                                        {{ $invoice['sap_ref'] }}
                                        <button type="button" x-on:click="navigator.clipboard?.writeText(@js(str($invoice['sap_ref'])->afterLast(' ')->toString()))" class="text-gray-400 hover:text-gray-600" title="Copy">
                                            <x-filament::icon icon="heroicon-m-clipboard-document" class="h-4 w-4" />
                                        </button>
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @foreach ($tabs as $key => [$label, $count])
                        @if ($count === 0)
                            <tr x-show="tab === '{{ $key }}'" x-cloak>
                                <td colspan="9" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">No invoices here.</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
