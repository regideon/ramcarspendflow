{{--
    Read invoice with AI: the review, laid out like the page's "Items for GR" table (same
    Filament table classes) so it reads the same. A ticked GR line is applied; red rows don't
    reconcile (Inv Qty differs from what was received, or the invoice line is on no GR line).
--}}
@php
    use App\Enums\P2pThreeWayMatchStatus;

    $match = $match ?? [];
    $rows = $rows ?? [];
    $matched = $match['rows'] ?? [];
    $files = $match['files'] ?? [];
    $statePath = isset($getStatePath) ? $getStatePath() : 'apply'; // the ViewField's state: ticks keyed by GR item id
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ','), '0'), '.') ?: '0';
    $price = fn ($v) => is_numeric($v) ? number_format((float) $v, 2) : '—';
    $pageTypes = ['invoice' => 'invoice', 'delivery_receipt' => 'delivery receipt', 'purchase_order' => 'PO copy', 'import_permit' => 'import permit', 'other' => 'other'];
@endphp

<div class="p2p-review">
    @if (collect($files)->contains('reused', true))
        <div class="p2p-review-box is-info">
            Some files were read before, so the saved result is shown. No new AI call was made for them.
        </div>
    @endif

    @if (filled($match['warnings'] ?? []))
        <div class="p2p-review-box is-warning">
            <strong>Check before applying</strong>
            <ul>
                @foreach ($match['warnings'] as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="p2p-review-meta">
        @foreach ($files as $file)
            <div><strong>{{ $file['name'] }}</strong>: {{ collect($file['pages'])->map(fn ($type) => $pageTypes[$type] ?? $type)->implode(', ') ?: 'no pages read' }}</div>
        @endforeach
        @foreach ($match['invoices'] ?? [] as $invoice)
            <div>Invoice <strong>{{ $invoice['invoice_number'] ?? '—' }}</strong> · {{ $invoice['invoice_date'] ?? 'no date' }} · {{ $invoice['currency'] ?? 'currency not printed' }} · total {{ $price($invoice['total_amount'] ?? null) }}</div>
        @endforeach
    </div>

    <div class="fi-fo-table-repeater p2p-review-table">
        <table>
            <thead>
                <tr>
                    <th class="fi-align-center">Apply</th>
                    <th>PO Number</th>
                    <th>GR Number</th>
                    <th>GR Item</th>
                    <th>SAP Short Text</th>
                    <th class="is-invoice">On the Invoice</th>
                    <th class="fi-align-end">PO Qty</th>
                    <th>Uom</th>
                    <th class="fi-align-end">GR Qty</th>
                    <th class="fi-align-end is-invoice">Inv Qty</th>
                    <th class="fi-align-end">Price</th>
                    <th class="fi-align-center">3-Way Matching</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $itemId => $row)
                    @php
                        $line = $matched[$itemId] ?? null;
                        $status = $line ? P2pThreeWayMatchStatus::from($line['status']) : P2pThreeWayMatchStatus::ForVerification;
                        $toBill = (float) $row['quantity_remaining'];
                        $differs = $line === null || (float) $line['quantity'] !== $toBill;
                    @endphp
                    <tr @class(['is-mismatch' => $differs])>
                        <td class="fi-align-center">
                            @if ($line)
                                <input type="checkbox" class="fi-checkbox-input" wire:model="{{ $statePath }}.{{ $itemId }}" aria-label="Apply GR {{ $row['gr_number'] }} item {{ $row['purchase_order_item'] }}" />
                            @endif
                        </td>
                        <td>{{ $row['purchase_order'] }}</td>
                        <td>{{ $row['gr_number'] }}</td>
                        <td>{{ $row['purchase_order_item'] }}</td>
                        <td>{{ $row['item_name'] }}</td>
                        <td>
                            @if ($line)
                                {{ $line['description'] ?? '—' }}
                                <div class="p2p-review-reason">Invoice {{ $line['invoice_number'] ?? '—' }}</div>
                            @else
                                <span class="p2p-review-muted">Not on the invoice</span>
                            @endif
                        </td>
                        <td class="fi-align-end">{{ $fmt($row['quantity_po'] ?? 0) }}</td>
                        <td>{{ $row['uom'] }}</td>
                        <td class="fi-align-end">
                            {{ $fmt($row['quantity_served'] ?? $toBill) }}
                            @if (($row['quantity_served'] ?? $toBill) > $toBill)
                                <div class="p2p-review-reason">{{ $fmt($toBill) }} left to bill</div>
                            @endif
                        </td>
                        <td class="fi-align-end">{{ $line ? $fmt($line['quantity']) : '—' }}</td>
                        <td class="fi-align-end">{{ $line ? $price($line['unit_price']) : '—' }}</td>
                        <td class="fi-align-center">
                            <x-filament::badge :color="$status->getColor()">{{ $status->getLabel() }}</x-filament::badge>
                            @foreach ($line['reasons'] ?? [] as $reason)
                                <div class="p2p-review-reason">{{ $reason }}</div>
                            @endforeach
                        </td>
                    </tr>
                @endforeach

                {{-- On the invoice but on none of these GR lines: shown, nothing to apply --}}
                @foreach ($match['unmatched_lines'] ?? [] as $line)
                    <tr class="is-mismatch">
                        <td></td>
                        <td colspan="4" class="p2p-review-muted">Not on the GR lines selected</td>
                        <td>
                            {{ $line['description'] ?? '—' }}
                            <div class="p2p-review-reason">Invoice {{ $line['invoice_number'] ?? '—' }}</div>
                        </td>
                        <td></td>
                        <td></td>
                        <td class="fi-align-end">—</td>
                        <td class="fi-align-end">{{ is_numeric($line['quantity'] ?? null) ? $fmt($line['quantity']) : '—' }}</td>
                        <td class="fi-align-end">{{ $price($line['unit_price'] ?? null) }}</td>
                        <td class="fi-align-center">
                            <x-filament::badge color="danger">Not on GR</x-filament::badge>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if (filled($match['charges'] ?? []))
        <div class="p2p-review-box">
            <strong>Charges not on any PO (shown only)</strong>
            <ul>
                @foreach ($match['charges'] as $charge)
                    <li>{{ $charge['invoice_number'] ?? '—' }}: {{ $charge['description'] ?? 'Charge' }} · {{ $price($charge['amount']) }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <style>
        .p2p-review { display: grid; gap: .75rem; font-size: .875rem; }
        .p2p-review-box { border-radius: .5rem; padding: .625rem .875rem; background: var(--gray-50); color: var(--gray-700); }
        .dark .p2p-review-box { background: var(--gray-800); color: var(--gray-200); }
        .p2p-review-box ul { margin: .25rem 0 0; padding-left: 1.125rem; list-style: disc; }
        .p2p-review-box.is-info { background: color-mix(in oklab, var(--info-500) 12%, transparent); }
        .p2p-review-box.is-warning { background: color-mix(in oklab, var(--warning-500) 15%, transparent); }
        .p2p-review-meta { display: grid; gap: .125rem; color: var(--gray-600); }
        .dark .p2p-review-meta { color: var(--gray-300); }

        /* Same table as the page; scroll sideways on narrow screens */
        .p2p-review-table { overflow-x: auto; }
        .p2p-review-table > table { display: table; min-width: 64rem; }
        .p2p-review-table > table > thead { display: table-header-group; }
        .p2p-review-table > table > thead > tr > th { white-space: nowrap; }
        .p2p-review-table > table > thead > tr > th.is-invoice { background: color-mix(in oklab, var(--warning-500) 22%, transparent); }
        .p2p-review-table > table > thead > tr > th.fi-align-center { text-align: center; }
        .p2p-review-table > table > tbody > tr > td { padding: .5rem .75rem; vertical-align: top; border-top: 1px solid var(--gray-200); }
        .dark .p2p-review-table > table > tbody > tr > td { border-color: color-mix(in oklab, var(--color-white) 10%, transparent); }
        .p2p-review-table td.fi-align-end { text-align: end; white-space: nowrap; }
        .p2p-review-table td.fi-align-center { text-align: center; }
        .p2p-review-table > table > tbody > tr.is-mismatch > td { background: color-mix(in oklab, var(--danger-500) 10%, transparent); }
        .p2p-review-reason { margin-top: .25rem; font-size: .75rem; color: var(--gray-500); }
        .p2p-review-table td.fi-align-center .p2p-review-reason { max-width: 16rem; margin-inline: auto; }
        .p2p-review-muted { color: var(--gray-500); font-style: italic; }
    </style>
</div>
