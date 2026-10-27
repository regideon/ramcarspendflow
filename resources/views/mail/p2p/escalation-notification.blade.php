@extends('mail.p2p.layouts.base')

@section('title', 'Escalation Notice')

@section('content')
    @php
        /** @var \App\Models\P2p\Escalation $escalation */
        $invoice = $escalation->invoice;
        $state = $escalation->goodReceiptState;
        $isOrigin = ($audience ?? 'hop') === 'origin';
        $currentTeam = \App\Models\P2p\Escalation::roleLabel($escalation->current_role);
        $originTeam = \App\Models\P2p\Escalation::roleLabel($escalation->origin_role);
        $isVendorRecipient = $isOrigin
            ? $escalation->origin_role === 'vendor'
            : $escalation->current_role === 'vendor';
        $greeting = $isVendorRecipient
            ? ($invoice?->vendor?->name ?? 'Vendor')
            : $currentTeam.' Team';
        $references = array_values(array_filter($invoice?->reference_number ?? []));
    @endphp

    <div class="email-wrapper">
        <p>Dear {{ $greeting }},</p>

        @if ($isOrigin)
            <p>Your escalation request has been received and forwarded to the {{ $currentTeam }} team for review.</p>
        @else
            <p>This escalation requires action from your team.</p>
        @endif

        <div class="section">
            <div class="section-title">Escalation Details</div>
            <table class="info-table">
                <tr>
                    <td>Reason:</td>
                    <td><strong>{{ $escalation->reason?->name ?? 'Escalation' }}</strong></td>
                </tr>
                <tr>
                    <td>Request No.:</td>
                    <td>{{ $escalation->requestNumber() }}</td>
                </tr>
                <tr>
                    <td>Current hop:</td>
                    <td>{{ $currentTeam }}</td>
                </tr>
                <tr>
                    <td>Raised by:</td>
                    <td>{{ $originTeam }}</td>
                </tr>
                <tr>
                    <td>GRC No.:</td>
                    <td>{{ $invoice?->grc_number ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Vendor:</td>
                    <td>{{ $invoice?->vendor?->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Company:</td>
                    <td>{{ $invoice?->company?->name ?? 'N/A' }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Invoice</div>
            <table class="info-table">
                <tr>
                    <td>Invoice No.:</td>
                    <td>{{ $invoice?->invoice_number ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Invoice Date:</td>
                    <td>{{ $invoice?->invoice_at?->format('M d, Y') ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Reference No.:</td>
                    <td>{{ $references !== [] ? implode(', ', $references) : 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Amount Submitted:</td>
                    <td><strong class="highlight">{{ number_format((float) ($invoice?->amount_submitted ?? 0), 2) }}</strong></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Purchase Orders & Goods Receipts</div>
            <table class="info-table">
                <tr>
                    <td>PO Numbers:</td>
                    <td>{{ implode(', ', $invoice?->po_numbers ?? []) ?: 'N/A' }}</td>
                </tr>
                <tr>
                    <td>GR Numbers:</td>
                    <td>{{ implode(', ', $invoice?->gr_numbers ?? []) ?: 'N/A' }}</td>
                </tr>
                @if ($state !== null)
                    <tr>
                        <td>GR Item:</td>
                        <td>{{ $state->purchase_order }} / {{ $state->good_receipt_number }}</td>
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
