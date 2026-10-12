@php
    $record = $record ?? (isset($getRecord) ? $getRecord() : null);
    $isVendor = auth()->user()?->hasRole(\App\Enums\RoleName::Vendor) ?? false;

    // Reuse the eager-loaded relation when the caller provides it, otherwise fall
    // back to an ordered query.
    $histories = $record
        ? ($record->relationLoaded('histories')
            ? $record->histories->sortByDesc('created_at')->values()
            : $record->histories()
                ->with('actionedBy.roles')
                ->orderBy('created_at', 'desc')
                ->get())
        : collect();

    $actionLabel = fn ($history): string => filled($history->action_label)
        ? $history->action_label
        : (filled($history->escalation_reason)
            ? 'Escalated by'
            : match ($history->to_status) {
                \App\Enums\P2pStatus::SubmittedToAp, \App\Enums\P2pStatus::SubmittedToChecker => 'Submitted by',
                \App\Enums\P2pStatus::InvoiceParked, \App\Enums\P2pStatus::InvoicePosted, \App\Enums\P2pStatus::Completed => 'Approved by',
                \App\Enums\P2pStatus::Rejected, \App\Enums\P2pStatus::RejectedLegacy => 'Rejected by',
                \App\Enums\P2pStatus::EscalateToProcurement, \App\Enums\P2pStatus::EscalatedToWarehouse, \App\Enums\P2pStatus::PartiallyEscalated => 'Escalated by',
                \App\Enums\P2pStatus::ReturnedToVendor, \App\Enums\P2pStatus::ReturnedToApProcessor => 'Returned by',
                \App\Enums\P2pStatus::Cancelled => 'Cancelled by',
                default => 'Updated by',
            });

    $dotColor = fn ($history): string => match ($history->to_status) {
        \App\Enums\P2pStatus::InvoiceParked, \App\Enums\P2pStatus::InvoicePosted, \App\Enums\P2pStatus::Completed => '#10b981',
        \App\Enums\P2pStatus::Rejected, \App\Enums\P2pStatus::RejectedLegacy, \App\Enums\P2pStatus::Reversed,
        \App\Enums\P2pStatus::EscalateToProcurement, \App\Enums\P2pStatus::EscalatedToWarehouse, \App\Enums\P2pStatus::PartiallyEscalated => '#ef4444',
        \App\Enums\P2pStatus::ReturnedToApProcessor => '#f59e0b',
        \App\Enums\P2pStatus::SubmittedToAp, \App\Enums\P2pStatus::SubmittedToChecker => '#3b82f6',
        default => '#9ca3af',
    };
@endphp

@if ($histories->isNotEmpty())
    <div style="display:flex; flex-direction:column;">
        @foreach ($histories as $history)
            <div style="display:flex; gap:12px;">
                {{-- Timeline dot + connector --}}
                <div style="display:flex; flex-direction:column; align-items:center;">
                    <span style="flex-shrink:0; width:10px; height:10px; margin-top:5px; border-radius:9999px; background-color:{{ $dotColor($history) }};"></span>
                    @unless ($loop->last)
                        <span style="flex-grow:1; width:2px; margin-top:4px; background-color:#e5e7eb;"></span>
                    @endunless
                </div>

                {{-- Entry --}}
                <div style="padding-bottom:{{ $loop->last ? '0' : '16px' }}; min-width:0;">
                    @php
                        $actor = $history->actionedBy;
                        $role = $actor?->roles?->first()?->name;
                    @endphp

                    <p style="font-size:0.875rem; color:#111827; margin:0;">
                        {{ $actionLabel($history) }}
                        <span style="font-weight:700;">
                            {{ \Illuminate\Support\Str::title($actor->name ?? 'Unknown User') }}@if ($role) ({{ \Illuminate\Support\Str::title($role) }})@endif
                        </span>
                    </p>

                    <p style="font-size:0.75rem; color:#6b7280; margin:2px 0 0; font-style:italic;">
                        Date: {{ $history->created_at->format('F j, Y h:iA') }}
                    </p>

                    @php
                        $isEscalation = filled($history->escalation_reason);
                        // Vendors only see entries marked visible to them.
                        $showRemarks = filled($history->remarks) && (! $isVendor || $history->is_visible_to_vendor);
                    @endphp

                    @if ($isEscalation || $showRemarks)
                        <div style="font-size:0.875rem; color:#111827; margin-top:8px; padding:10px 14px; border-radius:8px; background-color:rgba(128,128,128,.08); border-left:3px solid {{ $dotColor($history) }};">
                            @if ($isEscalation)
                                <div><strong>Reason:</strong> {{ $history->escalation_reason }}</div>
                            @endif

                            @if ($showRemarks)
                                <div style="margin-top:{{ $isEscalation ? '4px' : '0' }};">
                                    @if ($isEscalation)
                                        <strong>Remarks:</strong>
                                    @endif
                                    {{ $history->remarks }}
                                </div>
                            @endif

                            @if (filled($history->escalated_items))
                                <div style="margin-top:4px;"><strong>Escalated Items:</strong></div>
                                @foreach ($history->escalated_items as $escalatedItem)
                                    <div>- {{ is_array($escalatedItem) ? ($escalatedItem['good_receipt_number'] ?? '') : $escalatedItem }}</div>
                                @endforeach
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@else
    <p style="font-size:0.875rem; color:#6b7280; text-align:center; margin:0;">
        No approval history available
    </p>
@endif
