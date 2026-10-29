@props(['paginator', 'noun' => 'results'])

{{-- Footer of a Livewire list (WithPagination + a public $perPage): counts, rows per page, page links. --}}
@if ($paginator->total())
    <div class="flex flex-col items-center justify-between gap-4 border-t border-gray-100 bg-gray-50/40 px-6 py-4 text-xs text-gray-500 sm:flex-row dark:border-white/5 dark:bg-white/[0.02] dark:text-gray-400">
        <div class="flex items-center gap-3">
            <span class="tabular-nums">Showing <strong class="text-gray-900 dark:text-white">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</strong> of <strong class="text-gray-900 dark:text-white">{{ number_format($paginator->total()) }}</strong> {{ $noun }}</span>
            <span class="text-gray-300 dark:text-gray-600">|</span>
            <label class="flex items-center gap-1.5">
                Rows per page
                <select wire:model.live="perPage" class="rounded-lg border-0 bg-white py-1 ps-2.5 pe-7 text-xs font-semibold text-gray-900 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:text-white dark:ring-white/10">
                    @foreach ([10, 25, 50] as $size)
                        <option value="{{ $size }}">{{ $size }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        @if ($paginator->hasPages())
            @php
                $current = $paginator->currentPage();
                $last = $paginator->lastPage();
                $pages = collect([1, $last, ...range(max(1, $current - 1), min($last, $current + 1))])->unique()->sort()->values();
            @endphp
            <nav class="flex items-center gap-1.5" aria-label="Pagination">
                <button type="button" wire:click="previousPage" @disabled($paginator->onFirstPage()) class="rounded-lg px-3 py-1.5 font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-40 dark:text-gray-300 dark:hover:bg-white/5">Previous</button>
                @foreach ($pages as $page)
                    @if (! $loop->first && $page - $pages[$loop->index - 1] > 1)
                        <span class="px-1">…</span>
                    @endif
                    <button
                        type="button"
                        wire:click="gotoPage({{ $page }})"
                        @class([
                            'flex size-8 items-center justify-center rounded-lg text-xs tabular-nums',
                            'bg-primary-600 font-bold text-white shadow-sm' => $page === $current,
                            'font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' => $page !== $current,
                        ])
                    >{{ $page }}</button>
                @endforeach
                <button type="button" wire:click="nextPage" @disabled(! $paginator->hasMorePages()) class="rounded-lg px-3 py-1.5 font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-40 dark:text-gray-300 dark:hover:bg-white/5">Next</button>
            </nav>
        @endif
    </div>
@endif
