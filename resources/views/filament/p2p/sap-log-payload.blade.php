@php
    /** @var \App\Models\P2p\SapApiLog $log */
    $successful = $log->http_status !== null
        && (int) $log->http_status >= 200
        && (int) $log->http_status < 300;

    $statusClass = match (true) {
        $log->http_status === null => 'bg-gray-100 text-gray-700 ring-gray-500/20 dark:bg-gray-500/10 dark:text-gray-300',
        $successful => 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-400',
        default => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400',
    };

    $execution = $log->execution_time !== null
        ? number_format((float) $log->execution_time * 1000).' ms'
        : '—';
@endphp

<div class="space-y-6">
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <p class="text-[0.7rem] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</p>
            <span class="mt-2 inline-flex items-center rounded-md px-2.5 py-0.5 text-sm font-semibold ring-1 ring-inset {{ $statusClass }}">
                {{ $log->http_status ?? 'N/A' }}
            </span>
        </div>

        <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <p class="text-[0.7rem] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Exec time</p>
            <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $execution }}</p>
        </div>

        <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <p class="text-[0.7rem] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Type</p>
            <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                {{ $log->type === \App\Models\P2p\SapApiLog::TYPE_ODATA ? 'OData (read)' : 'SOAP (write)' }}
            </p>
        </div>

        <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <p class="text-[0.7rem] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Logged at</p>
            <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $log->created_at?->format('M d, Y h:i A') }}</p>
        </div>
    </div>

    <div>
        <p class="text-[0.7rem] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Endpoint</p>
        <p class="mt-1 break-all font-mono text-sm text-gray-900 dark:text-gray-100">{{ $log->endpoint }}</p>
    </div>

    @if ($log->invoice)
        <div>
            <p class="text-[0.7rem] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoice</p>
            <a
                href="{{ \App\Filament\Pages\P2p\BulkAccountsPayable::getUrl(['invoice' => $log->invoice->id]) }}"
                class="mt-1 inline-flex text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
            >
                {{ $log->invoice->grc_number }} · {{ $log->invoice->status->getLabel() }}
            </a>
        </div>
    @endif

    @foreach (['Request' => $log->request, 'Response' => $log->response] as $heading => $payload)
        <div>
            <h4 class="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $heading }}</h4>

            @if (filled($payload))
                <pre class="max-h-72 overflow-auto whitespace-pre-wrap break-words rounded-xl bg-gray-900 p-4 text-xs leading-relaxed text-gray-100 ring-1 ring-white/10">{{ $payload }}</pre>
            @else
                <p class="rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-500 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
                    No payload recorded.
                </p>
            @endif
        </div>
    @endforeach

    @if (filled($log->error_message))
        <div>
            <h4 class="mb-2 text-sm font-semibold text-red-600 dark:text-red-400">Error</h4>
            <pre class="max-h-72 overflow-auto whitespace-pre-wrap break-words rounded-xl bg-red-950/60 p-4 text-xs leading-relaxed text-red-100 ring-1 ring-red-500/20">{{ $log->error_message }}</pre>
        </div>
    @endif
</div>
