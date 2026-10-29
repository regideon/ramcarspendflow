@extends('mail.p2p.layouts.base')

@section('title', 'Overdue Escalation Warning')

@section('content')
    @php
        $invoice = $escalation->invoice;
        $days = (int) ($escalation->escalated_at?->diffInDays(now()) ?? 0);
    @endphp

    <div class="email-wrapper">
        <p>Dear Procurement Team,</p>

        <p>This escalation has been open for <strong class="highlight">{{ $days }} days</strong> and requires
            immediate attention.</p>

        <div class="section">
            <div class="section-title">Escalation Details</div>
            <table class="info-table">
                <tr>
                    <td>Escalation:</td>
                    <td>{{ $escalation->requestNumber() }}</td>
                </tr>
                <tr>
                    <td>Reason:</td>
                    <td>{{ $escalation->reason?->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Current hop:</td>
                    <td>{{ \App\Models\P2p\Escalation::roleLabel($escalation->current_role) }}</td>
                </tr>
                <tr>
                    <td>Vendor:</td>
                    <td>{{ $invoice?->vendor?->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Company:</td>
                    <td>{{ $invoice?->company?->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Submitted At:</td>
                    <td>{{ $invoice?->submitted_at?->format('M d, Y h:i A') ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Days Open:</td>
                    <td><strong class="highlight">{{ $days }} days</strong></td>
                </tr>
                <tr>
                    <td>GR Numbers:</td>
                    <td>{{ implode(', ', $invoice?->gr_numbers ?? []) ?: 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Reference No.:</td>
                    <td>{{ implode(', ', $invoice?->reference_number ?? []) ?: 'N/A' }}</td>
                </tr>
                <tr>
                    <td>PO Numbers:</td>
                    <td>{{ implode(', ', $invoice?->po_numbers ?? []) ?: 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Invoice Date:</td>
                    <td>{{ $invoice?->invoice_at?->format('M d, Y') ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Amount Submitted:</td>
                    <td><strong class="highlight">{{ number_format((float) ($invoice?->amount_submitted ?? 0), 2) }}</strong></td>
                </tr>
                @if (filled($invoice?->invoice_number))
                    <tr>
                        <td>Invoice Number:</td>
                        <td>{{ $invoice->invoice_number }}</td>
                    </tr>
                @endif
            </table>
        </div>

        @if (filled($escalation->remarks))
            <div class="section">
                <div class="section-title">Remarks</div>
                <div class="remarks">{{ $escalation->remarks }}</div>
            </div>
        @endif

        @if (filled($shareUrl))
            <div class="cta-container">
                <a href="{{ $shareUrl }}" class="cta-button">View Request</a>
            </div>
        @endif

        @include('mail.p2p.layouts.footer')
    </div>
@endsection
