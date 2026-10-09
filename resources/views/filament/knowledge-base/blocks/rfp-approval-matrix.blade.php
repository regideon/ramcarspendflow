@php
    use App\Enums\RfpRequestType;
    use App\Models\Rfp\ApprovalRule;

    $rules = ApprovalRule::query()
        ->with('steps.role')
        ->where('is_active', true)
        ->get();

    $groups = [
        'Reimbursement' => $rules->filter(fn ($rule) => ! $rule->is_direct_to_vp && $rule->request_type === RfpRequestType::Reimbursement),
        'Standard' => $rules->filter(fn ($rule) => ! $rule->is_direct_to_vp && $rule->request_type === RfpRequestType::Standard),
        'Direct to VP (any request type)' => $rules->filter(fn ($rule) => $rule->is_direct_to_vp),
    ];

    $money = fn (string $amount): string => number_format((float) $amount, 2);
@endphp

@foreach ($groups as $heading => $tiers)
    @continue($tiers->isEmpty())

    <h3>{{ $heading }}</h3>

    <table>
        <thead>
            <tr>
                <th>PHP amount</th>
                <th>Approvers, in order</th>
            </tr>
        </thead>
        <tbody>
            @php($previousMax = null)
            @foreach ($tiers->sortBy(fn ($rule) => $rule->max_amount === null ? PHP_FLOAT_MAX : (float) $rule->max_amount) as $rule)
                <tr>
                    <td>
                        @if ($rule->max_amount === null)
                            Above {{ $previousMax === null ? '0.00' : $money($previousMax) }}
                        @elseif ($previousMax === null)
                            Up to {{ $money($rule->max_amount) }}
                        @else
                            {{ $money(bcadd($previousMax, '0.01', 2)) }} to {{ $money($rule->max_amount) }}
                        @endif
                    </td>
                    <td>
                        {{ $rule->steps->groupBy('step_order')->map(fn ($steps) => $steps->pluck('role.name')->implode(' + '))->implode(' → ') }}
                    </td>
                </tr>
                @php($previousMax = $rule->max_amount)
            @endforeach
        </tbody>
    </table>
@endforeach

<p class="kb-block-caption">This table is read live from <em>Settings → RFP Settings → Approval Matrix</em>, so it always shows the current rules.</p>
