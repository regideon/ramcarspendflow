@extends('mail.p2p.layouts.base')

@section('title', $heading ?? 'P2P Notification')

@section('content')
    <div class="email-wrapper">
        <h2 style="margin-top:0;color:#1f2937;">{{ $heading }}</h2>

        <p>{{ $body }}</p>

        @if ($escalation)
            <div class="section">
                <div class="section-title">Escalation</div>
                <table class="info-table">
                    <tr>
                        <td>Escalation:</td>
                        <td>{{ $escalation->escalation_number }}</td>
                    </tr>
                    <tr>
                        <td>Reason:</td>
                        <td>{{ $escalation->reason?->name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td>Current hop:</td>
                        <td>{{ \App\Models\P2p\Escalation::roleLabel($escalation->current_role) }}</td>
                    </tr>
                </table>
            </div>
        @endif

        @include('mail.p2p.layouts.footer')
    </div>
@endsection
