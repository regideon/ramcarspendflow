@extends('mail.p2p.layouts.base')

@section('title', 'Request Cancelled')

@section('content')
    <div class="email-wrapper">
        <p>Dear {{ $invoice->vendor?->name ?? 'Vendor' }},</p>

        <p>Your goods receipt request has been <strong>cancelled</strong>. The goods receipts covered by this request
            have been returned to Pending and can be reused in a new request. This request is now closed and can no
            longer be used.</p>

        <div class="section">
            <div class="section-title">Request Details</div>
            <table class="info-table">
                <tr>
                    <td>GR Numbers:</td>
                    <td>{{ implode(', ', $invoice->gr_numbers ?? []) ?: 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Reference No.:</td>
                    <td>{{ implode(', ', $invoice->reference_number ?? []) ?: 'N/A' }}</td>
                </tr>
                <tr>
                    <td>PO Numbers:</td>
                    <td>{{ implode(', ', $invoice->po_numbers ?? []) ?: 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Vendor:</td>
                    <td>{{ $invoice->vendor?->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Company:</td>
                    <td>{{ $invoice->company?->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Cancelled On:</td>
                    <td>{{ now()->format('M d, Y h:i A') }}</td>
                </tr>
            </table>
        </div>

        @if (filled($invoice->remarks_ap))
            <div class="section">
                <div class="section-title">Remarks (AP)</div>
                <div class="remarks">{{ $invoice->remarks_ap }}</div>
            </div>
        @endif

        <p>No further action is required from your side at this time.</p>

        @include('mail.p2p.layouts.footer')
    </div>
@endsection
