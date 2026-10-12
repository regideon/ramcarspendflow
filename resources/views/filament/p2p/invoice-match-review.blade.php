@php
    use App\Enums\P2pThreeWayMatchStatus;

    $match = $match ?? [];
    $rows = $rows ?? [];
    $matched = $match['rows'] ?? [];
    $files = $match['files'] ?? [];
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ','), '0'), '.') ?: '0';
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
            <div>Invoice <strong>{{ $invoice['invoice_number'] ?? '—' }}</strong> · {{ $invoice['invoice_date'] ?? 'no date' }} · total {{ $invoice['currency'] ?? '' }} {{ is_numeric($invoice['total_amount'] ?? null) ? number_format((float) $invoice['total_amount'], 2) : '—' }}</div>
        @endforeach
    </div>

    <div class="p2p-review-table-wrap">
        <table class="p2p-review-table">
            <thead>
                <tr>
                    <th>GR / item</th>
                    <th>Short text</th>
                    <th>On the invoice</th>
                    <th class="num">GR Qty</th>
                    <th class="num">Inv Qty</th>
                    <th class="num">Price</th>
                    <th>3-way</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $itemId => $row)
                    @php
                        $line = $matched[$itemId] ?? null;
                        $status = $line ? P2pThreeWayMatchStatus::from($line['status']) : P2pThreeWayMatchStatus::ForVerification;
                    @endphp
                    <tr>
                        <td>{{ $row['gr_number'] }} · {{ $row['purchase_order_item'] }}</td>
                        <td>{{ $row['item_name'] }}</td>
                        <td>{{ $line ? ($line['description'] ?? '—').' ('.($line['invoice_number'] ?? '—').')' : 'Not on the invoice' }}</td>
                        <td class="num">
                            {{ $fmt($row['quantity_served'] ?? $row['quantity_remaining']) }} {{ $row['uom'] }}
                            @if (($row['quantity_served'] ?? $row['quantity_remaining']) > $row['quantity_remaining'])
                                <div class="p2p-review-reason">{{ $fmt($row['quantity_remaining']) }} left to bill</div>
                            @endif
                        </td>
                        <td @class(['num', 'p2p-review-diff' => $line && (float) $line['quantity'] !== (float) $row['quantity_remaining']])>{{ $line ? $fmt($line['quantity']) : '—' }}</td>
                        <td class="num">{{ $line && $line['unit_price'] !== null ? ($line['currency'] ?? '').' '.number_format((float) $line['unit_price'], 2) : '—' }}</td>
                        <td>
                            <x-filament::badge :color="$status->getColor()">{{ $status->getLabel() }}</x-filament::badge>
                            @foreach ($line['reasons'] ?? [] as $reason)
                                <div class="p2p-review-reason">{{ $reason }}</div>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if (filled($match['unmatched_lines'] ?? []))
        <div class="p2p-review-box is-danger">
            <strong>Invoice lines not matched to any GR line</strong>
            <ul>
                @foreach ($match['unmatched_lines'] as $line)
                    <li>{{ $line['invoice_number'] ?? '—' }}: {{ $line['description'] ?? '—' }} · qty {{ $fmt($line['quantity'] ?? 0) }} @ {{ is_numeric($line['unit_price'] ?? null) ? number_format((float) $line['unit_price'], 2) : '—' }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (filled($match['charges'] ?? []))
        <div class="p2p-review-box">
            <strong>Charges not on any PO (shown only)</strong>
            <ul>
                @foreach ($match['charges'] as $charge)
                    <li>{{ $charge['invoice_number'] ?? '—' }}: {{ $charge['description'] ?? 'Charge' }} · {{ number_format((float) $charge['amount'], 2) }}</li>
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
        .p2p-review-box.is-danger { background: color-mix(in oklab, var(--danger-500) 12%, transparent); }
        .p2p-review-meta { display: grid; gap: .125rem; color: var(--gray-600); }
        .dark .p2p-review-meta { color: var(--gray-300); }
        .p2p-review-table-wrap { overflow-x: auto; }
        .p2p-review-table { width: 100%; border-collapse: collapse; }
        .p2p-review-table th { text-align: left; font-size: .75rem; text-transform: uppercase; color: var(--gray-500); padding: .375rem .5rem; border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
        .p2p-review-table td { padding: .5rem; border-bottom: 1px solid var(--gray-100); vertical-align: top; }
        .dark .p2p-review-table th, .dark .p2p-review-table td { border-color: var(--gray-700); }
        .p2p-review-table .num { text-align: right; white-space: nowrap; }
        .p2p-review-diff { font-weight: 600; color: var(--warning-600); }
        .p2p-review-reason { margin-top: .25rem; font-size: .75rem; color: var(--gray-500); max-width: 18rem; }
    </style>
</div>
