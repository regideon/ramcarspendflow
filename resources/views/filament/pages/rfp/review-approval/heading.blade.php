{{-- Page heading: RFP number, status and who holds it now. Rendered inside Filament's <h1>. --}}
<span class="flex flex-wrap items-center gap-x-3 gap-y-1">
    <span>{{ $rfp->rfp_number }}</span>
    @include('filament.pages.rfp.review-approval.status-badge', ['status' => $rfp->status])
    @if ($with = $rfp->currentApproverLabel())
        <span class="inline-flex items-center gap-1 text-sm font-normal tracking-normal text-gray-500 dark:text-gray-400">
            <x-filament::icon icon="heroicon-m-clock" class="size-4" />
            Currently with: <span class="font-semibold text-gray-900 dark:text-white">{{ $with }}</span>
        </span>
    @endif
</span>
