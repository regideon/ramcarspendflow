{{-- Read invoice with AI: what to check before the 3-way review (same content as the old callout) --}}
<div class="rounded-2xl border-s-4 border-warning-500 bg-warning-50/90 p-5 shadow-sm dark:bg-warning-500/10" data-tour="match">
    <div class="flex items-start gap-3">
        <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-warning-100 text-warning-800 dark:bg-warning-500/20 dark:text-warning-300">
            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="size-5" />
        </div>
        <div class="min-w-0 flex-1">
            <h2 class="font-display text-base font-bold leading-tight text-gray-900 dark:text-white">
                {{ $count > 0 ? $count.' '.str('line')->plural($count).' to check against the vendor\'s invoice' : 'Check the vendor\'s invoice' }}
            </h2>
            <p class="mt-0.5 text-sm text-gray-700 dark:text-gray-300">Compare the purchase order, goods receipt and the vendor's invoice before you submit.</p>

            <div class="mt-4 space-y-3 text-sm">
                @foreach ($groups as $title => $items)
                    <div class="rounded-xl bg-white/70 p-3 shadow-sm dark:bg-white/5">
                        <span class="mb-0.5 block text-[0.6875rem] font-bold uppercase tracking-wide text-warning-900 dark:text-warning-300">{{ $title }}</span>
                        @foreach ($items as $item)
                            <div class="flex items-start gap-2 text-gray-800 dark:text-gray-200">
                                <span class="mt-2 size-1.5 shrink-0 rounded-full bg-warning-500"></span>
                                <p>{{ $item }}</p>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
