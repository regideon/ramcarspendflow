@php
    use App\Models\Rfp\LegacyImportRun;

    $connection = $this->legacyConnection();
    $runs = $this->runs();
    $isRunning = LegacyImportRun::isRunning();
@endphp

<x-filament-panels::page>
    <div @if ($isRunning) wire:poll.5s @endif class="grid gap-6">
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="danger" heading="Before go-live only">
            <div class="grid gap-2 text-sm text-gray-700 dark:text-gray-200">
                <p>The import <strong>replaces every RFP in this system</strong> with the data in the legacy RFP Portal, including RFPs created or approved here. Users, roles and master data are updated, and RFPs still in approval get their approval route, so they can be worked here.</p>
                <p>After go-live, set <code class="rounded bg-gray-100 px-1 dark:bg-white/10">RFP_LEGACY_IMPORT_ENABLED=false</code> in <code class="rounded bg-gray-100 px-1 dark:bg-white/10">.env</code>. This page and the <code class="rounded bg-gray-100 px-1 dark:bg-white/10">rfp:import-legacy</code> command are then locked.</p>
            </div>
        </x-filament::section>

        <x-filament::section heading="Source">
            <dl class="grid gap-4 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Legacy database</dt>
                    <dd class="font-medium text-gray-950 dark:text-white">{{ $connection['database'] }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Connection</dt>
                    <dd>
                        @if ($connection['connected'])
                            <x-filament::badge color="success">Connected · {{ number_format($connection['legacyRfps']) }} RFPs</x-filament::badge>
                        @else
                            <x-filament::badge color="danger">Cannot connect</x-filament::badge>
                            <p class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ \Illuminate\Support\Str::limit($connection['error'], 200) }}</p>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">RFPs in this system now</dt>
                    <dd class="font-medium text-gray-950 dark:text-white">{{ number_format($this->rfpsHere()) }} <span class="text-xs font-normal text-gray-500">(replaced on import)</span></dd>
                </div>
            </dl>
        </x-filament::section>

        <x-filament::section heading="Recent runs">
            @forelse ($runs as $run)
                <details @class(['border-t border-gray-200 py-3 first:border-0 first:pt-0 dark:border-white/10']) @if ($loop->first) open @endif>
                    <summary class="flex cursor-pointer flex-wrap items-center gap-3 text-sm">
                        <x-filament::badge :color="match ($run->status) { LegacyImportRun::SUCCEEDED => 'success', LegacyImportRun::FAILED => 'danger', default => 'warning' }">
                            {{ ucfirst($run->status) }}
                        </x-filament::badge>
                        <span class="font-medium text-gray-950 dark:text-white">{{ $run->created_at->format('M j, Y g:i A') }}</span>
                        <span class="text-gray-500 dark:text-gray-400">by {{ $run->started_by }}</span>
                        @if ($run->finished_at)
                            <span class="text-gray-500 dark:text-gray-400">· took {{ $run->created_at->diffForHumans($run->finished_at, true) }}</span>
                        @endif
                    </summary>
                    @if ($run->output)
                        <pre class="mt-3 max-h-96 overflow-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{{ $run->output }}</pre>
                    @elseif ($run->status === LegacyImportRun::RUNNING)
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Running… this page refreshes every few seconds.</p>
                    @endif
                </details>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">No imports run from this page yet.</p>
            @endforelse
        </x-filament::section>
    </div>
</x-filament-panels::page>
