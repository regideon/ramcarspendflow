@php
    $steps = [
        ['Requestor', false],
        ['AP Processor', false],
        ['Approvers (matrix)', true],
        ['AP Processor', false],
        ['AP Supervisor', false],
        ['AP Manager', false],
        ['Treasury', false],
    ];
@endphp

<div class="kb-route not-prose" role="list" aria-label="RFP approval route">
    @foreach ($steps as $index => [$label, $isMatrix])
        <span role="listitem" @class(['kb-route-step', 'kb-route-step-matrix' => $isMatrix])>
            <span class="kb-route-step-number">{{ $index + 1 }}</span>
            {{ $label }}
        </span>

        @unless ($loop->last)
            <span class="kb-route-arrow" aria-hidden="true">→</span>
        @endunless
    @endforeach
</div>
