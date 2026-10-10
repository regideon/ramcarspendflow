<h1>{{ $heading }}</h1>

<p>{{ $body }}</p>

@if ($escalation)
    <ul>
        <li><strong>Escalation:</strong> {{ $escalation->escalation_number }}</li>
        <li><strong>Reason:</strong> {{ $escalation->reason?->name }}</li>
        <li><strong>Current hop:</strong> {{ $escalation->current_role }}</li>
    </ul>
@endif

<p>{{ config('app.name') }}</p>
