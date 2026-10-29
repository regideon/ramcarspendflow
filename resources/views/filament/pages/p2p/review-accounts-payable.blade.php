@php
    /**
     * Redesigned AP review (Google Stitch). Logic is BulkAccountsPayable's; see ReviewAccountsPayable.
     */
    use App\Filament\Pages\P2p\ApprovalQueue;
    use App\Support\Peso;

    $invoice = $this->invoiceModel();
    $documents = $this->documents();
    $actions = collect($this->getFooterActions());
    $cancel = $actions->first(fn ($action): bool => $action->getName() === 'back');
    $decisions = $actions->reject(fn ($action): bool => $action->getName() === 'back')->reverse()->values();
    $statusColor = $invoice?->status?->getColor() ?? 'gray';
    $amount = fn (?string $key): float => (float) str_replace(',', '', (string) ($this->data[$key] ?? 0));
@endphp

<x-filament-panels::page class="sf-ap-review">
    {{-- Header: where it is, what it is, how much --}}
    <div class="sf-card -mt-2 flex flex-col justify-between gap-4 px-6 py-4 md:flex-row md:items-center">
        <div class="flex flex-col gap-1">
            <nav class="flex items-center gap-1.5 text-[0.6875rem] font-medium text-gray-500 dark:text-gray-400" aria-label="Breadcrumb">
                <span>P2P</span>
                <x-filament::icon icon="heroicon-m-chevron-right" class="size-3.5" />
                <a href="{{ ApprovalQueue::getUrl() }}" class="hover:text-gray-900 hover:underline dark:hover:text-white">P2P Approvals</a>
                <x-filament::icon icon="heroicon-m-chevron-right" class="size-3.5" />
                <span class="font-semibold text-gray-900 dark:text-white">{{ $invoice?->grc_number }}</span>
            </nav>
            <div class="flex flex-wrap items-center gap-2">
                @if ($invoice?->status)
                    <span @class([
                        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                        'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300' => $statusColor === 'warning',
                        'bg-danger-100 text-danger-700 dark:bg-danger-500/15 dark:text-danger-300' => $statusColor === 'danger',
                        'bg-success-100 text-success-800 dark:bg-success-500/15 dark:text-success-300' => $statusColor === 'success',
                        'bg-primary-100 text-primary-800 dark:bg-primary-500/15 dark:text-primary-300' => in_array($statusColor, ['info', 'primary'], true),
                        'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300' => $statusColor === 'gray',
                    ])>
                        <span @class(['size-2 rounded-full', 'animate-pulse bg-warning-500' => ! $this->isViewOnly, 'bg-gray-400' => $this->isViewOnly])></span>
                        {{ $invoice->status->getLabel() }}
                    </span>
                @endif
                <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-300">
                    <x-filament::icon icon="heroicon-m-building-office-2" class="size-3.5 text-primary-600" />
                    {{ $this->data['vendor_name'] ?? '—' }}
                </span>
                @if ($this->isViewOnly)
                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">
                        <x-filament::icon icon="heroicon-m-eye" class="size-3.5" />
                        View only: not in your queue
                    </span>
                @endif
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Review the vendor's invoice against the PO and goods receipt, set the tax, then submit.</p>
        </div>

        <div class="flex items-center gap-3">
            <div class="flex flex-col items-end rounded-xl bg-gray-50 px-4 py-2 dark:bg-white/5">
                <span class="text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total</span>
                <span class="font-display text-lg font-bold tabular-nums text-gray-900 dark:text-white">{{ Peso::format($amount('total')) }}</span>
            </div>
            <div class="flex flex-col items-end rounded-xl bg-primary-50 px-4 py-2 dark:bg-primary-500/10">
                <span class="text-[0.6875rem] font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-300">Net payable</span>
                <span class="font-display text-lg font-bold tabular-nums text-primary-700 dark:text-primary-300">{{ Peso::format($amount('net')) }}</span>
            </div>
        </div>
    </div>

    <form wire:submit="submit">
        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
            {{-- Left: the vendor's invoice, kept in view while checking the values --}}
            <div class="lg:sticky lg:top-20 lg:col-span-5" data-tour="viewer">
                <div class="sf-card overflow-hidden">
                    <div class="flex items-center gap-2 bg-gray-50/70 px-4 py-3 dark:bg-white/[0.03]">
                        <div class="flex size-8 items-center justify-center rounded-lg bg-primary-100 text-primary-600 dark:bg-primary-500/15">
                            <x-filament::icon icon="heroicon-m-paper-clip" class="size-4" />
                        </div>
                        <div>
                            <span class="block text-sm font-semibold leading-tight text-gray-900 dark:text-white">Attached Invoice</span>
                            <span class="text-[0.6875rem] text-gray-500 dark:text-gray-400">{{ count($documents) === 1 ? 'The vendor\'s invoice' : count($documents).' files from the vendor' }}</span>
                        </div>
                    </div>
                    <div class="sf-ap-viewer">
                        @include('filament.rfps.receipt-document-viewer', ['documents' => $documents, 'emptyMessage' => 'No invoice attachment uploaded.'])
                    </div>
                </div>
            </div>

            {{-- Right: the review --}}
            <div class="space-y-6 lg:col-span-7">
                {{ $this->form }}
            </div>
        </div>
    </form>

    {{-- Sticky decision bar: Cancel on the left, the decision on the right --}}
    <div class="sf-ap-actionbar" data-tour="actions">
        <div class="flex w-full items-center justify-between gap-3">
            <div>
                @if ($cancel)
                    {{ $cancel->icon('heroicon-m-arrow-left')->link() }}
                @endif
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2">
                @foreach ($decisions as $action)
                    {{ $action }}
                @endforeach
            </div>
        </div>
    </div>

    <x-filament-actions::modals />

    <x-user-guide :steps="$this->guideSteps()" />
</x-filament-panels::page>
