@php
    /**
     * The Items section: the submission's lines against the SAP PO/GR mirror, with a
     * per-line 3-way match badge. When the viewer is the AP Processor, each line also
     * carries a tick that escalates just that line.
     */
    $receipts = $receipts ?? [];
    $escalatable = $escalatable ?? false;

    $fmt = fn ($value): string => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') ?: '0';

    $palette = [
        'success' => ['bg' => '#dcfce7', 'text' => '#166534'],
        'warning' => ['bg' => '#fef3c7', 'text' => '#92400e'],
        'danger' => ['bg' => '#fee2e2', 'text' => '#991b1b'],
        'info' => ['bg' => '#dbeafe', 'text' => '#1e40af'],
    ];

    $colors = fn (string $c): array => $palette[$c] ?? ['bg' => '#f3f4f6', 'text' => '#374151'];

    $allItems = collect($receipts)->flatMap(fn (array $gr): array => $gr['goodReceiptItems'] ?? []);
@endphp

<div style="border:1px solid #e5e7eb; border-radius:10px; overflow:hidden;">
    @if ($escalatable)
        <div style="padding:10px 14px; background:#fffbeb; border-bottom:1px solid #fde68a; font-size:12px; color:#92400e;">
            Tick a line to include it when you escalate. Lines left unticked are not escalated.
        </div>
    @endif

    @forelse ($receipts as $grIndex => $gr)
        <div style="padding:10px 14px; border-bottom:1px solid #f3f4f6;" wire:key="gr-{{ $grIndex }}">
            <div style="font-size:12px; color:#374151; margin-bottom:6px;">
                <strong>GR {{ $gr['good_receipt_number'] ?? '—' }}</strong>
                <span style="color:#6b7280;"> &middot; PO {{ $gr['purchase_order'] ?? '—' }}</span>
            </div>

            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="text-align:left; color:#6b7280; font-size:11px; text-transform:uppercase;">
                        @if ($escalatable)
                            <th style="padding:4px 8px 4px 0; width:28px;">Esc.</th>
                        @endif
                        <th style="padding:4px 0;">Item</th>
                        <th style="padding:4px 0; text-align:right;">PO Qty</th>
                        <th style="padding:4px 0; text-align:right;">GR Qty</th>
                        <th style="padding:4px 0; text-align:right;">Inv Qty</th>
                        <th style="padding:4px 0; text-align:right;">Unit Cost</th>
                        <th style="padding:4px 0; text-align:right;">Amount</th>
                        <th style="padding:4px 0;">3-Way</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($gr['goodReceiptItems'] ?? [] as $itemIndex => $item)
                        @php $match = $item['match'] ?? []; @endphp
                        <tr style="border-top:1px solid #f3f4f6;" wire:key="gr-{{ $grIndex }}-item-{{ $itemIndex }}">
                            @if ($escalatable)
                                <td style="padding:4px 8px 4px 0;">
                                    <input
                                        type="checkbox"
                                        wire:model="goods_receipts.{{ $grIndex }}.goodReceiptItems.{{ $itemIndex }}.escalate"
                                        style="cursor:pointer;"
                                    >
                                </td>
                            @endif
                            <td style="padding:4px 0;">
                                <div>{{ $item['item_name'] ?? '—' }}</div>
                                <div style="font-size:11px; color:#6b7280;">
                                    PO {{ $item['purchase_order'] ?? '—' }} / {{ $item['purchase_order_item'] ?? '—' }}
                                    &middot; UoM {{ $item['uom'] ?? '—' }}
                                </div>
                            </td>
                            <td style="padding:4px 0; text-align:right;">{{ $fmt($item['quantity_po'] ?? 0) }}</td>
                            <td style="padding:4px 0; text-align:right;">{{ $fmt($item['quantity_served'] ?? 0) }}</td>
                            <td style="padding:4px 0; text-align:right; font-weight:600;">{{ $fmt($item['quantity_billed'] ?? 0) }}</td>
                            <td style="padding:4px 0; text-align:right;">₱{{ number_format((float) ($item['unit_cost'] ?? 0), 2) }}</td>
                            <td style="padding:4px 0; text-align:right;">₱{{ number_format((float) ($item['amount'] ?? 0), 2) }}</td>
                            <td style="padding:4px 0;">
                                @if (! empty($match['label']))
                                    <span style="border-radius:6px; padding:1px 8px; font-size:11px; font-weight:600; background:{{ $colors($match['color'] ?? 'gray')['bg'] }}; color:{{ $colors($match['color'] ?? 'gray')['text'] }};"
                                        title="{{ $match['reason'] ?? '' }}">
                                        {{ $match['label'] }}
                                    </span>
                                @else
                                    <span style="color:#9ca3af;">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <div style="padding:14px; font-size:13px; color:#6b7280;">
            No goods-receipt lines are linked to this submission.
        </div>
    @endforelse

    @if ($allItems->isNotEmpty())
        <div style="display:flex; flex-wrap:wrap; justify-content:flex-end; gap:12px 32px; align-items:center; font-weight:600; padding:12px 14px; background:#f9fafb;">
            <span>Total PO Qty: <span>{{ number_format((float) $allItems->sum(fn ($i) => (float) ($i['quantity_po'] ?? 0))) }}</span></span>
            <span>Total GR Qty: <span>{{ number_format((float) $allItems->sum(fn ($i) => (float) ($i['quantity_served'] ?? 0))) }}</span></span>
            <span>Total Inv Qty: <span>{{ number_format((float) $allItems->sum(fn ($i) => (float) ($i['quantity_billed'] ?? 0))) }}</span></span>
            <span>Total Amount: ₱{{ number_format((float) $allItems->sum(fn ($i) => (float) ($i['amount'] ?? 0)), 2) }}</span>
        </div>
    @endif
</div>
