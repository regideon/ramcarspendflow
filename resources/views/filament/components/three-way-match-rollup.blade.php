@php
    $record = $getRecord();
    $detail = app(\App\Services\P2p\P2pThreeWayMatchService::class)->breakdownForInvoice($record);

    $label = $detail['label'];
    $color = $detail['color'];
    $reason = $detail['reason'];
    $lines = $detail['lines'];

    $palette = [
        'success' => ['bg' => '#dcfce7', 'text' => '#166534'],
        'warning' => ['bg' => '#fef3c7', 'text' => '#92400e'],
        'danger' => ['bg' => '#fee2e2', 'text' => '#991b1b'],
        'info' => ['bg' => '#dbeafe', 'text' => '#1e40af'],
    ];

    $colors = fn (string $c): array => $palette[$c] ?? ['bg' => '#f3f4f6', 'text' => '#374151'];

    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') ?: '0';
@endphp

<div x-data="{ open: false }" style="display:inline-block;">
    <button
        type="button"
        x-on:click="open = true"
        title="Click for the 3-way breakdown"
        style="cursor:pointer;border:none;border-radius:6px;padding:2px 8px;font-size:12px;font-weight:600;background:{{ $colors($color)['bg'] }};color:{{ $colors($color)['text'] }};"
    >
        {{ $label }}
    </button>

    <template x-teleport="body">
        {{-- The centering wrapper is a separate element so Alpine's x-show can
             toggle the outer node's display without wiping the flex layout. --}}
        <div x-show="open" x-cloak style="position:fixed;inset:0;z-index:9999;overflow:auto;">
            <div style="min-height:100%;display:flex;align-items:center;justify-content:center;padding:16px;">
                <div x-on:click="open = false" style="position:fixed;inset:0;background:rgba(17,24,39,.5);"></div>

                <div style="position:relative;width:100%;max-width:760px;max-height:80vh;overflow:auto;background:#fff;border-radius:12px;padding:20px;box-shadow:0 20px 25px -5px rgba(0,0,0,.2);color:#111827;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                        <h3 style="margin:0;font-size:15px;font-weight:700;">3-Way Match — {{ $label }}</h3>
                        <button type="button" x-on:click="open = false" style="cursor:pointer;border:none;background:none;font-size:18px;line-height:1;color:#6b7280;">&times;</button>
                    </div>

                    <p style="margin:8px 0 0;font-size:13px;color:#4b5563;">{{ $reason }}</p>

                    <table style="width:100%;margin-top:14px;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="text-align:left;color:#6b7280;font-size:11px;text-transform:uppercase;">
                                <th style="padding:4px 0;">PO / Item</th>
                                <th style="padding:4px 0;text-align:right;">PO Qty</th>
                                <th style="padding:4px 0;text-align:right;">GR Qty</th>
                                <th style="padding:4px 0;text-align:right;">IR Qty</th>
                                <th style="padding:4px 0;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($lines as $line)
                                <tr style="border-top:1px solid #f3f4f6;">
                                    <td style="padding:4px 0;">{{ $line['po_number'] }} / {{ $line['po_item'] }}</td>
                                    <td style="padding:4px 0;text-align:right;">{{ $fmt($line['ordered']) }}</td>
                                    <td style="padding:4px 0;text-align:right;">{{ $fmt($line['received']) }}</td>
                                    <td style="padding:4px 0;text-align:right;">{{ $fmt($line['invoiced']) }}</td>
                                    <td style="padding:4px 0;">
                                        <span style="border-radius:6px;padding:1px 8px;font-size:11px;font-weight:600;background:{{ $colors($line['color'])['bg'] }};color:{{ $colors($line['color'])['text'] }};">
                                            {{ $line['label'] }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="padding:8px 0;color:#6b7280;">No GR lines linked to this submission.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div style="margin-top:16px;text-align:right;">
                        <button type="button" x-on:click="open = false" style="cursor:pointer;border:none;border-radius:8px;background:#f3f4f6;color:#374151;padding:6px 14px;font-size:13px;font-weight:600;">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
