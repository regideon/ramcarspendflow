@php
    use App\Support\Peso;
    use App\Support\SvgChart;

    ['rfp_review' => $review, 'rfp_processing' => $processing, 'p2p' => $p2p, 'returned' => $returned, 'overdue' => $overdue, 'processed' => $processed] = $stats;

    // tone: number colour; footTone: footer line colour (null = grey)
    $cards = [
        ['label' => 'RFP first review', 'value' => $review['count'], 'hint' => Peso::format($review['amount'], compact: true), 'foot' => "Oldest item {$review['oldest']}d", 'tone' => null, 'footTone' => null],
        ['label' => 'RFP AP processing', 'value' => $processing['count'], 'hint' => Peso::format($processing['amount'], compact: true), 'foot' => "Oldest item {$processing['oldest']}d", 'tone' => null, 'footTone' => $processing['oldest'] > 5 ? 'warning' : null],
        ['label' => 'P2P to process', 'value' => $p2p['count'], 'hint' => Peso::format($p2p['amount'], compact: true), 'foot' => "{$p2p['no_match']} not matched", 'tone' => null, 'footTone' => $p2p['no_match'] > 0 ? 'danger' : null],
        ['label' => 'Returned to me', 'value' => $returned['count'], 'hint' => 'Needs fix', 'foot' => "{$returned['rfp']} RFP · {$returned['p2p']} P2P · ".Peso::format($returned['amount'], compact: true), 'tone' => 'warning', 'footTone' => null],
        ['label' => 'Overdue', 'value' => $overdue['count'], 'hint' => Peso::format($overdue['amount'], compact: true), 'foot' => 'Past their due date', 'tone' => 'danger', 'footTone' => 'danger'],
    ];
@endphp

<section data-tour="kpis" class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
    @foreach ($cards as $card)
        <div @class([
            'sf-card flex flex-col justify-between p-5 transition',
            'hover:border-gray-300 dark:hover:border-white/20' => $card['tone'] !== 'danger',
            '!border-danger-200 bg-gradient-to-b from-danger-50/40 to-white hover:!border-danger-300 dark:!border-danger-500/30 dark:from-danger-500/10 dark:to-gray-900' => $card['tone'] === 'danger',
        ])>
            <div>
                <div class="flex items-center justify-between">
                    <span @class(['text-xs font-medium', 'text-danger-700 dark:text-danger-400' => $card['tone'] === 'danger', 'text-gray-500 dark:text-gray-400' => $card['tone'] !== 'danger'])>{{ $card['label'] }}</span>
                    @if ($card['tone'] === 'danger' && $card['value'])
                        <span class="size-2 animate-pulse rounded-full bg-danger-500"></span>
                    @endif
                </div>
                <div class="mt-2 flex flex-wrap items-baseline gap-x-2">
                    <span @class([
                        'font-display text-3xl font-extrabold tabular-nums',
                        'text-gray-950 dark:text-white' => $card['tone'] === null,
                        'text-warning-600 dark:text-warning-400' => $card['tone'] === 'warning',
                        'text-danger-600 dark:text-danger-400' => $card['tone'] === 'danger',
                    ])>{{ $card['value'] }}</span>
                    <span @class([
                        'text-[0.6875rem]',
                        'text-gray-400' => $card['tone'] === null,
                        'rounded bg-warning-50 px-1.5 py-0.5 font-medium text-warning-700 dark:bg-warning-500/10 dark:text-warning-400' => $card['tone'] === 'warning',
                        'font-medium text-danger-500' => $card['tone'] === 'danger',
                    ])>{{ $card['hint'] }}</span>
                </div>
            </div>
            <p @class([
                'mt-3 truncate border-t pt-2 text-[0.6875rem]',
                'border-gray-100 text-gray-500 dark:border-white/5 dark:text-gray-400' => $card['footTone'] === null,
                'border-gray-100 font-medium text-warning-700 dark:border-white/5 dark:text-warning-400' => $card['footTone'] === 'warning',
                'border-danger-100 font-medium text-danger-600 dark:border-danger-500/20 dark:text-danger-400' => $card['footTone'] === 'danger',
            ])>{{ $card['foot'] }}</p>
        </div>
    @endforeach

    {{-- Processed this week, with a sparkline of items finished per day --}}
    <div class="sf-card flex flex-col justify-between p-5 transition hover:border-gray-300 dark:hover:border-white/20">
        <div>
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Processed this week</span>
                <span @class([
                    'rounded px-1.5 py-0.5 text-[0.625rem] font-bold tabular-nums',
                    'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $processed['avg_change'] <= 0,
                    'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400' => $processed['avg_change'] > 0,
                ])>{{ sprintf('%+.1fd', $processed['avg_change']) }}</span>
            </div>
            <div class="mt-2 flex items-baseline justify-between gap-2">
                <span class="font-display text-3xl font-extrabold tabular-nums text-gray-950 dark:text-white">{{ $processed['count'] }}</span>
                <svg class="h-7 w-16 text-success-500" viewBox="0 0 64 28" fill="none" aria-hidden="true">
                    <polygon points="{{ SvgChart::area($processedPerDay, 64, 28, min: null) }}" fill="currentColor" fill-opacity="0.1" />
                    <polyline points="{{ SvgChart::points($processedPerDay, 64, 28, min: null) }}" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>
        </div>
        <p class="mt-3 truncate border-t border-gray-100 pt-2 text-[0.6875rem] font-medium text-success-700 dark:border-white/5 dark:text-success-400">{{ sprintf('%.1f', $processed['avg_days']) }}d avg in your hands</p>
    </div>
</section>
