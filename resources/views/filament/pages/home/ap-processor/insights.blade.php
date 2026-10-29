@php
    $improving = $returnRate['change'] <= 0;
    $topReason = $returnReasons[0] ?? null;
    $finished = collect($decisions)->sum('count');

    $tips = [
        [
            'icon' => $improving ? 'heroicon-o-check-circle' : 'heroicon-o-arrow-trending-up',
            'tile' => $improving ? 'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-400' : 'bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
            'title' => "Return rate {$returnRate['rate']}%",
            'body' => "The AP Supervisor sent back {$returnRate['rate']}% of your work, ".abs($returnRate['change']).' pts '.($improving ? 'less' : 'more').' than last period.',
        ],
        [
            'icon' => 'heroicon-o-exclamation-triangle',
            'tile' => 'bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
            'title' => $topReason ? "Most returns: {$topReason['reason']}" : 'No returns yet',
            'body' => $returnReasons
                ? collect($returnReasons)->map(fn (array $reason): string => "{$reason['reason']} ({$reason['count']})")->implode(' · ').'. Check these before you send an item on.'
                : 'Nothing was sent back to you this period.',
        ],
        [
            'icon' => 'heroicon-o-document-check',
            'tile' => 'bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300',
            'title' => "{$finished} items finished this period",
            'body' => collect($decisions)->map(fn (array $decision): string => "{$decision['count']} {$decision['label']}")->implode(' · ').'.',
        ],
    ];
@endphp

<section data-tour="insights" class="sf-card p-6">
    <x-sf.card-header heading="Insights" description="How your finished work went, from the AP Supervisor's returns and your decisions" class="mb-4" />

    <div class="grid gap-5 md:grid-cols-3">
        @foreach ($tips as $tip)
            <div class="flex items-start gap-3.5 rounded-xl border border-gray-200/70 bg-gray-50 p-4 dark:border-white/5 dark:bg-white/5">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $tip['tile'] }}">
                    <x-filament::icon :icon="$tip['icon']" class="size-5" />
                </div>
                <div>
                    <h4 class="text-xs font-bold text-gray-950 dark:text-white">{{ $tip['title'] }}</h4>
                    <p class="mt-1 text-xs leading-relaxed text-gray-600 dark:text-gray-400">{{ $tip['body'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
</section>
