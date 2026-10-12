@php
    $status = $status ?? 'no_match';
    $label = $label ?? '—';
    $color = $color ?? 'gray';
    $reason = $reason ?? '';
    $ordered = (float) ($ordered ?? 0);
    $received = (float) ($received ?? 0);
    $invoiced = (float) ($invoiced ?? 0);
    $rows = $rows ?? [];
    $adjustment = (float) ($adjustment ?? 0);
    $notes = $notes ?? []; // what the vendor's invoice said (Read invoice with AI)

    $palette = [
        'success' => ['bg' => '#dcfce7', 'text' => '#166534'],
        'warning' => ['bg' => '#fef3c7', 'text' => '#92400e'],
        'danger' => ['bg' => '#fee2e2', 'text' => '#991b1b'],
        'info' => ['bg' => '#dbeafe', 'text' => '#1e40af'],
    ][$color] ?? ['bg' => '#f3f4f6', 'text' => '#374151'];

    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') ?: '0';
@endphp

<div x-data="{ open: false }" style="display:flex; justify-content:center;">
    <button
        type="button"
        x-on:click="open = true"
        title="Click for the 3-way breakdown"
        style="cursor:pointer;border:none;border-radius:6px;padding:2px 8px;font-size:12px;font-weight:600;background:{{ $palette['bg'] }};color:{{ $palette['text'] }};"
    >
        {{ $label }}
    </button>

    <template x-teleport="body">
        {{-- The centering wrapper is a separate element so Alpine's x-show can
             toggle the outer node's display without wiping the flex layout. --}}
        <div x-show="open" x-cloak style="position:fixed;inset:0;z-index:9999;overflow:auto;">
            <div style="min-height:100%;display:flex;align-items:center;justify-content:center;padding:16px;">
                <div x-on:click="open = false" style="position:fixed;inset:0;background:rgba(17,24,39,.5);"></div>

                <div style="position:relative;width:100%;max-width:560px;background:#fff;border-radius:12px;padding:20px;box-shadow:0 20px 25px -5px rgba(0,0,0,.2);color:#111827;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                        <h3 style="margin:0;font-size:15px;font-weight:700;">3-Way Match — {{ $label }}</h3>
                        <button type="button" x-on:click="open = false" style="cursor:pointer;border:none;background:none;font-size:18px;line-height:1;color:#6b7280;">&times;</button>
                    </div>

                    <p style="margin:8px 0 0;font-size:13px;color:#4b5563;">{{ $reason }}</p>

                    @if (filled($notes))
                        <ul style="margin:10px 0 0;padding:10px 12px 10px 28px;background:#f9fafb;border-radius:8px;font-size:13px;color:#374151;list-style:disc;">
                            @foreach ($notes as $note)
                                <li>{{ $note }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:14px;">
                        <div style="background:#f9fafb;border-radius:8px;padding:10px;">
                            <div style="font-size:11px;text-transform:uppercase;color:#6b7280;">PO Qty</div>
                            <div style="font-weight:700;">{{ $fmt($ordered) }}</div>
                        </div>
                        <div style="background:#f9fafb;border-radius:8px;padding:10px;">
                            <div style="font-size:11px;text-transform:uppercase;color:#6b7280;">GR Qty</div>
                            <div style="font-weight:700;">{{ $fmt($received) }}</div>
                        </div>
                        <div style="background:#f9fafb;border-radius:8px;padding:10px;">
                            <div style="font-size:11px;text-transform:uppercase;color:#6b7280;">IR Qty</div>
                            <div style="font-weight:700;">{{ $fmt($invoiced) }}</div>
                        </div>
                    </div>

                    <table style="width:100%;margin-top:14px;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="text-align:left;color:#6b7280;font-size:11px;text-transform:uppercase;">
                                <th style="padding:4px 0;">GR #</th>
                                <th style="padding:4px 0;text-align:right;">GR Qty</th>
                                <th style="padding:4px 0;text-align:right;">IR Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr style="border-top:1px solid #f3f4f6;">
                                    <td style="padding:4px 0;">{{ $row['gr_number'] }}</td>
                                    <td style="padding:4px 0;text-align:right;">{{ $fmt($row['gr']) }}</td>
                                    <td style="padding:4px 0;text-align:right;">{{ $fmt($row['ir']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" style="padding:8px 0;color:#6b7280;">No GR lines linked to this PO line yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    @if ($adjustment > 0)
                        <p style="margin:10px 0 0;font-size:12px;color:#6b7280;">
                            IR includes {{ $fmt($adjustment) }} from the pending quantity being submitted now.
                        </p>
                    @endif

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
