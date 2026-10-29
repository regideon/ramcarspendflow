@php
    use App\Enums\RfpRequestType;
    use App\Enums\RfpStatus;
    use App\Filament\My\Pages\Approvals;
    use App\Filament\My\Pages\Dashboard;
    use App\Filament\My\Pages\RfpDetail;
    use App\Filament\My\Pages\MyRequests;
    use Filament\Support\Icons\Heroicon;

    $rfp = $this->rfp();
    $status = $rfp['status'];
    $isApprover = $this->isApproverView();
    $isMine = $this->isMine();
    $items = $this->items();
    $totals = $this->totals();
    $timeline = $this->timeline();
    $messages = $this->messages();
    $requestor = $this->requestorName();

    $peso = fn (float $amount): string => '₱'.number_format($amount, 2);
    $backUrl = $isMine ? MyRequests::getUrl() : Approvals::getUrl($isApprover ? [] : ['tab' => 'done']);
    $backLabel = $isMine ? 'My requests' : 'Approvals';

    $stageChip = match ($status) {
        RfpStatus::Draft => 'Not submitted',
        RfpStatus::ForRevision => 'Returned',
        RfpStatus::Completed => 'Completed',
        RfpStatus::Rejected => 'Rejected',
        RfpStatus::Cancelled => 'Cancelled',
        default => 'Stage '.MyRequests::stageOf($status).' of '.count(MyRequests::STAGES),
    };

    $currentApprover = collect($timeline[1]['approvers'])->firstWhere('state', 'current');
    $currentStage = collect($timeline)->firstWhere('state', 'current');
    $waitingOn = match (true) {
        $currentApprover !== null => 'Waiting for '.$currentApprover['role'].' approval',
        $currentStage !== null => $currentStage['note'] ?? 'With '.$currentStage['name'],
        default => null,
    };

    $isDueSoon = $rfp['dueDate']->lte(today()->addDays(3)) && ! in_array($status, RfpStatus::final(), true);
@endphp

<div
    x-data="{ sheet: @js($this->initialSheet()), remarks: '', menu: false, openItem: null, copied: false }"
    @keydown.escape.window="sheet = null; menu = false"
    class="flex flex-col gap-4 lg:gap-6"
>
    {{-- Mobile header --}}
    <header class="flex items-center justify-between gap-2 lg:hidden">
        <a href="{{ $backUrl }}" aria-label="Back to {{ $backLabel }}" class="my-glass flex size-10 shrink-0 items-center justify-center rounded-full text-slate-700 transition active:scale-95">
            <x-filament::icon :icon="Heroicon::ChevronLeft" class="size-5" />
        </a>

        <button type="button" x-on:click="navigator.clipboard?.writeText(@js($rfp['number'])); copied = true; setTimeout(() => copied = false, 1500)" class="my-glass flex items-center gap-1.5 rounded-full px-3 py-1.5">
            <span class="font-mono text-xs font-semibold tracking-tight text-slate-900">{{ $rfp['number'] }}</span>
            <x-filament::icon x-show="! copied" :icon="Heroicon::OutlinedDocumentDuplicate" class="size-3.5 text-slate-400" />
            <x-filament::icon x-cloak x-show="copied" :icon="Heroicon::Check" class="size-3.5 text-emerald-600" />
        </button>

        <div class="relative">
            <button type="button" x-on:click="menu = ! menu" aria-label="More options" class="my-glass flex size-10 items-center justify-center rounded-full text-slate-700 transition active:scale-95">
                <x-filament::icon :icon="Heroicon::EllipsisHorizontal" class="size-5" />
            </button>
            <div x-cloak x-show="menu" x-on:click.outside="menu = false" x-transition class="absolute top-12 right-0 z-30 flex w-48 flex-col rounded-xl border border-white bg-white/95 p-1.5 shadow-xl backdrop-blur-2xl">
                <a href="#" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                    <x-filament::icon :icon="Heroicon::OutlinedArrowDownTray" class="size-4 text-slate-400" />
                    Download PDF
                </a>
                <button type="button" x-on:click="navigator.clipboard?.writeText(window.location.href); menu = false" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-left text-xs font-medium text-slate-700 hover:bg-slate-50">
                    <x-filament::icon :icon="Heroicon::OutlinedLink" class="size-4 text-slate-400" />
                    Copy link
                </button>
                @if ($isMine && ! in_array($status, RfpStatus::final(), true))
                    <span class="my-1 h-px bg-slate-200/70"></span>
                    <a href="#" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50">
                        <x-filament::icon :icon="Heroicon::OutlinedXCircle" class="size-4" />
                        Cancel request
                    </a>
                @endif
            </div>
        </div>
    </header>

    {{-- Laptop breadcrumb --}}
    <header class="hidden items-center justify-between gap-4 lg:flex">
        <nav class="flex items-center gap-2 text-sm">
            <a href="{{ $backUrl }}" class="text-slate-500 transition hover:text-indigo-600">{{ $backLabel }}</a>
            <span class="text-slate-300">/</span>
            <span class="font-mono font-semibold text-slate-900">{{ $rfp['number'] }}</span>
            <x-my.status-pill :status="$status" />
        </nav>
        <div class="flex items-center gap-2">
            <a href="#" class="my-glass flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-white">
                <x-filament::icon :icon="Heroicon::OutlinedArrowDownTray" class="size-4 text-slate-400" />
                Download PDF
            </a>
            <button type="button" x-on:click="navigator.clipboard?.writeText(window.location.href); copied = true; setTimeout(() => copied = false, 1500)" class="my-glass flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-white">
                <x-filament::icon :icon="Heroicon::OutlinedLink" class="size-4 text-slate-400" />
                <span x-text="copied ? 'Copied' : 'Copy link'">Copy link</span>
            </button>
        </div>
    </header>

    {{-- Returned to the requestor --}}
    @if ($isMine && $status === RfpStatus::ForRevision)
        <div class="flex items-center justify-between gap-3 rounded-2xl border border-orange-200/70 bg-orange-50/90 p-3.5 shadow-sm backdrop-blur-md">
            <p class="flex items-start gap-2 text-xs leading-snug text-orange-900">
                <x-filament::icon :icon="Heroicon::ExclamationTriangle" class="mt-0.5 size-4 shrink-0 text-orange-600" />
                <span>{{ $rfp['note'] }}</span>
            </p>
            <a href="#" class="shrink-0 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white shadow-sm active:scale-95">Edit &amp; resubmit</a>
        </div>
    @endif

    {{-- Hero --}}
    <section class="relative overflow-hidden rounded-[20px] border border-white/90 bg-gradient-to-br from-indigo-500/15 via-violet-300/25 to-white/80 p-4 shadow-[inset_0_1px_1px_0_rgba(255,255,255,0.8),0_12px_36px_0_rgba(99,102,241,0.08)] backdrop-blur-xl lg:p-7">
        <div class="pointer-events-none absolute -top-16 -right-16 size-56 rounded-full border border-indigo-200/40"></div>

        <div class="relative flex flex-col gap-4 lg:flex-row lg:items-stretch lg:justify-between lg:gap-8">
            <div class="flex min-w-0 flex-col gap-3">
                <div class="flex flex-wrap items-center gap-1.5">
                    <x-my.status-pill :status="$status" class="lg:hidden" />
                    <span class="rounded-full bg-white/70 px-2 py-0.5 text-[10px] font-semibold text-slate-600">{{ $rfp['type']->getLabel() }}</span>
                    <span @class([
                        'flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold',
                        'bg-amber-50 text-amber-700 ring-1 ring-amber-200' => $isDueSoon,
                        'bg-white/70 text-slate-600' => ! $isDueSoon,
                    ])>
                        <x-filament::icon :icon="Heroicon::OutlinedCalendar" class="size-3" />
                        Due {{ $rfp['dueDate']->format('M j, Y') }}
                    </span>
                </div>

                <div>
                    <h1 class="font-display text-lg font-semibold tracking-tight text-slate-900 lg:text-3xl lg:font-bold">{{ $rfp['purpose'] }}</h1>
                    <p class="mt-1 font-display text-3xl font-extrabold tracking-tight text-slate-900 tabular-nums lg:hidden">{{ $peso($totals['gross']) }}</p>
                </div>

                <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500 lg:text-sm">
                    @if ($rfp['type'] === RfpRequestType::Reimbursement)
                        <span class="font-medium text-slate-800">Reimbursement to {{ $rfp['payee'] ?? $requestor }}</span>
                    @else
                        <span class="font-medium text-slate-800">{{ $rfp['payee'] }}</span>
                    @endif
                    <span class="size-1 rounded-full bg-slate-300"></span>
                    <span>Ramcar Inc.</span>
                </p>

                <div class="flex items-center gap-2.5 rounded-xl bg-white/50 p-2.5 lg:self-start lg:bg-transparent lg:p-0">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-tr from-indigo-600 to-violet-500 text-[11px] font-bold text-white shadow-sm">
                        {{ str($requestor)->explode(' ')->map(fn (string $part): string => $part[0])->take(2)->join('') }}
                    </span>
                    <span class="flex flex-col">
                        <span class="text-sm leading-tight font-semibold text-slate-900">{{ $requestor }}</span>
                        <span class="text-[11px] text-slate-500">
                            Requestor ·
                            {{ $status === RfpStatus::Draft ? 'Not submitted yet' : 'Submitted '.$rfp['date']->format('M j, Y') }}
                        </span>
                    </span>
                </div>

                @if (! $isApprover && filled($waitingOn))
                    <p class="flex items-center gap-2.5 rounded-xl bg-amber-500/10 px-3 py-2 text-xs font-semibold text-slate-800 lg:self-start">
                        <span class="relative flex size-2.5">
                            <span class="absolute inline-flex size-full animate-ping rounded-full bg-amber-500 opacity-75"></span>
                            <span class="relative inline-flex size-2.5 rounded-full bg-amber-500"></span>
                        </span>
                        {{ $waitingOn }}
                    </p>
                @endif
            </div>

            {{-- Laptop: amount and the approver's decision buttons --}}
            <div class="hidden shrink-0 flex-col items-end justify-between gap-6 lg:flex">
                <div class="flex flex-col items-end">
                    <span class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Gross amount</span>
                    <span class="mt-1 font-display text-[2.5rem] leading-none font-extrabold tracking-tight text-slate-900 tabular-nums">{{ $peso($totals['gross']) }}</span>
                    <span class="mt-2 text-xs text-slate-500">Net payable after EWT {{ $peso($totals['net']) }}</span>
                </div>

                @if ($isApprover)
                    <div class="flex items-center gap-2">
                        <button type="button" x-on:click="sheet = 'return'" class="flex items-center gap-1.5 rounded-xl border border-orange-200 bg-orange-50/80 px-3.5 py-2 text-sm font-semibold text-orange-700 transition hover:bg-orange-100">
                            <x-filament::icon :icon="Heroicon::ArrowUturnLeft" class="size-4" />
                            Return
                        </button>
                        <button type="button" x-on:click="sheet = 'reject'" class="flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50/80 px-3.5 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100">
                            <x-filament::icon :icon="Heroicon::XMark" class="size-4" />
                            Reject
                        </button>
                        <button type="button" x-on:click="sheet = 'approve'" class="flex items-center gap-1.5 rounded-xl bg-gradient-to-b from-emerald-500 to-emerald-600 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.25),0_8px_20px_-6px_rgba(5,150,105,0.55)] px-5 py-2 text-sm font-semibold transition hover:from-emerald-400 hover:to-emerald-500">
                            <x-filament::icon :icon="Heroicon::CheckBadge" class="size-4 text-white" />
                            Approve
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-12 lg:gap-6">
        <div class="flex flex-col gap-4 lg:col-span-8 lg:gap-6">
            <div class="lg:hidden">
                @include('filament.my.pages.rfp-detail.timeline')
            </div>

            {{-- Items --}}
            <section class="my-glass rounded-[20px] p-4 lg:p-6">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-base font-bold text-slate-900 lg:text-lg">Items ({{ count($items) }})</h2>
                    <span class="font-mono text-[11px] text-slate-400">PHP</span>
                </div>

                {{-- Phones: tap a row for its tax breakdown --}}
                <ul class="mt-3 flex flex-col gap-2 lg:hidden">
                    @foreach ($items as $index => $item)
                        <li class="rounded-xl bg-white/60">
                            <button type="button" x-on:click="openItem = openItem === {{ $index }} ? null : {{ $index }}" class="flex w-full items-start justify-between gap-2 p-3 text-left">
                                <span class="min-w-0">
                                    <span class="line-clamp-1 text-sm font-semibold text-slate-900">{{ $item['description'] }}</span>
                                    <span class="font-mono text-[11px] text-slate-400">Inv #{{ $item['invoice'] }} · {{ $item['invoiceDate']->format('M j') }}</span>
                                </span>
                                <span class="flex shrink-0 items-center gap-1">
                                    <span class="font-display text-sm font-bold text-slate-900 tabular-nums">{{ $peso($item['amount']) }}</span>
                                    <x-filament::icon :icon="Heroicon::ChevronDown" class="size-4 text-slate-400 transition" x-bind:class="openItem === {{ $index }} && 'rotate-180'" />
                                </span>
                            </button>
                            <dl x-cloak x-show="openItem === {{ $index }}" x-collapse class="mx-3 mb-3 flex flex-col gap-1.5 rounded-lg bg-white/80 p-2.5 text-[11px] text-slate-500">
                                <div class="flex justify-between"><dt>{{ $item['subcategory'] }}</dt><dd class="font-mono">{{ $item['po'] ?? 'No PO' }}</dd></div>
                                <div class="flex justify-between"><dt>Cost center</dt><dd class="font-mono">{{ $item['costCenter'] }}</dd></div>
                                <div class="flex justify-between"><dt>VAT (12%)</dt><dd class="font-mono tabular-nums">{{ $peso($item['vat']) }}</dd></div>
                                <div class="flex justify-between"><dt>EWT (1%)</dt><dd class="font-mono text-rose-600 tabular-nums">−{{ $peso($item['ewt']) }}</dd></div>
                                <div class="flex justify-between font-semibold text-slate-800"><dt>Net payable</dt><dd class="font-mono tabular-nums">{{ $peso($item['net']) }}</dd></div>
                            </dl>
                        </li>
                    @endforeach
                </ul>

                {{-- Laptops: table --}}
                <table class="mt-4 hidden w-full text-left text-xs lg:table">
                    <thead>
                        <tr class="bg-white/50 text-[10px] tracking-wider text-slate-400 uppercase">
                            <th class="rounded-l-lg px-3 py-2.5 font-semibold">Description &amp; vendor</th>
                            <th class="px-3 py-2.5 font-semibold">Invoice</th>
                            <th class="px-3 py-2.5 text-right font-semibold">VAT</th>
                            <th class="px-3 py-2.5 text-right font-semibold">EWT</th>
                            <th class="rounded-r-lg px-3 py-2.5 text-right font-semibold">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100/70">
                        @foreach ($items as $item)
                            <tr class="transition-colors hover:bg-white/60">
                                <td class="max-w-64 px-3 py-3">
                                    <span class="block truncate font-medium text-slate-900">{{ $item['description'] }}</span>
                                    <span class="mt-0.5 flex items-center gap-1.5 text-[11px] text-slate-400">
                                        <span class="truncate">{{ $item['vendor'] }}</span>
                                        <span class="shrink-0 rounded bg-slate-100 px-1.5 font-mono text-[10px] text-slate-600">{{ $item['costCenter'] }}</span>
                                    </span>
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <span class="block font-mono text-slate-700">{{ $item['invoice'] }}</span>
                                    <span class="text-[11px] text-slate-400">{{ $item['invoiceDate']->format('M j, Y') }}</span>
                                </td>
                                <td class="px-3 py-3 text-right font-mono whitespace-nowrap text-slate-500 tabular-nums">{{ $peso($item['vat']) }}</td>
                                <td class="px-3 py-3 text-right font-mono whitespace-nowrap text-rose-600 tabular-nums">−{{ $peso($item['ewt']) }}</td>
                                <td class="px-3 py-3 text-right font-display text-sm font-bold whitespace-nowrap text-slate-900 tabular-nums">{{ $peso($item['amount']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <dl class="mt-3 flex flex-col gap-1 rounded-xl bg-white/50 p-3 text-xs text-slate-500 lg:ml-auto lg:w-80">
                    <div class="flex justify-between"><dt>Gross amount</dt><dd class="font-mono text-slate-800 tabular-nums">{{ $peso($totals['gross']) }}</dd></div>
                    <div class="flex justify-between"><dt>VAT included (12%)</dt><dd class="font-mono tabular-nums">{{ $peso($totals['vat']) }}</dd></div>
                    <div class="flex justify-between"><dt>EWT (1%)</dt><dd class="font-mono text-rose-600 tabular-nums">−{{ $peso($totals['ewt']) }}</dd></div>
                    <div class="mt-1 flex items-baseline justify-between border-t border-slate-200/70 pt-2 font-semibold text-slate-900">
                        <dt>Net payable</dt>
                        <dd class="font-display text-lg font-bold tabular-nums">{{ $peso($totals['net']) }}</dd>
                    </div>
                </dl>
            </section>

            {{-- Attachments --}}
            <section class="my-glass rounded-[20px] p-4 lg:p-6">
                <h2 class="font-display text-base font-bold text-slate-900 lg:text-lg">Attachments ({{ count($rfp['attachments']) }})</h2>

                <div class="-mx-1 mt-3 flex gap-2.5 overflow-x-auto px-1 pb-1 lg:grid lg:grid-cols-3 lg:gap-3">
                    @foreach ($rfp['attachments'] as $file)
                        <a href="#" class="flex w-40 shrink-0 flex-col justify-between gap-3 rounded-xl bg-white/60 p-3 transition hover:bg-white lg:w-auto">
                            <span class="flex items-center justify-between">
                                <span @class([
                                    'flex size-8 items-center justify-center rounded-lg',
                                    'bg-rose-50 text-rose-600' => $file['kind'] === 'pdf',
                                    'bg-indigo-50 text-indigo-600' => $file['kind'] !== 'pdf',
                                ])>
                                    <x-filament::icon :icon="$file['kind'] === 'pdf' ? Heroicon::OutlinedDocumentText : Heroicon::OutlinedPhoto" class="size-[18px]" />
                                </span>
                                <span class="text-[11px] font-medium text-slate-400">{{ $file['size'] }}</span>
                            </span>
                            <span>
                                <span class="block truncate text-xs font-semibold text-slate-900">{{ $file['name'] }}</span>
                                <span class="mt-0.5 block text-[11px] text-slate-500">{{ $file['label'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>

            {{-- Details --}}
            <details class="group my-glass rounded-[20px] p-4 lg:p-6">
                <summary class="flex cursor-pointer list-none items-center justify-between [&::-webkit-details-marker]:hidden">
                    <h2 class="font-display text-base font-bold text-slate-900 lg:text-lg">Details</h2>
                    <x-filament::icon :icon="Heroicon::ChevronDown" class="size-5 text-slate-400 transition group-open:rotate-180" />
                </summary>
                <dl class="mt-3 grid grid-cols-2 gap-2 lg:grid-cols-3">
                    @foreach ($this->details() as $label => $value)
                        <div class="flex flex-col rounded-xl bg-white/60 p-2.5">
                            <dt class="text-[11px] text-slate-500">{{ $label }}</dt>
                            <dd class="text-xs font-semibold text-slate-900">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </details>

            <div class="lg:hidden">
                @include('filament.my.pages.rfp-detail.messages')
            </div>

            @if ($isApprover)
                {{-- Room for the action bar on phones --}}
                <div class="h-20 lg:hidden"></div>
            @endif
        </div>

        <div class="hidden flex-col gap-6 lg:sticky lg:top-8 lg:col-span-4 lg:flex">
            @include('filament.my.pages.rfp-detail.timeline')
            @include('filament.my.pages.rfp-detail.messages')
        </div>
    </div>

    @if ($isApprover)
        {{-- Phones: decision bar above the floating nav (clear of its raised New RFP button) --}}
        <div class="h-20 lg:hidden"></div>
        <div class="fixed inset-x-0 bottom-[calc(max(1.5rem,env(safe-area-inset-bottom))+6rem)] z-40 px-4 lg:hidden">
            <div class="mx-auto flex max-w-xl items-center gap-2 rounded-2xl bg-gradient-to-br from-slate-950 via-indigo-950 to-indigo-800 p-2.5 shadow-[0_24px_56px_-10px_rgba(30,27,75,0.65),0_8px_20px_-8px_rgba(15,23,42,0.45)] ring-1 ring-white/15">
                <button type="button" x-on:click="sheet = 'return'" class="flex items-center gap-1 rounded-xl bg-gradient-to-b from-orange-400 to-orange-500 px-3 py-2.5 text-xs font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.3),0_8px_18px_-6px_rgba(249,115,22,0.7)] active:scale-95">
                    <x-filament::icon :icon="Heroicon::ArrowUturnLeft" class="size-4 text-white" />
                    Return
                </button>
                <button type="button" x-on:click="sheet = 'reject'" class="flex items-center gap-1 rounded-xl bg-gradient-to-b from-rose-500 to-rose-600 px-3 py-2.5 text-xs font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.3),0_8px_18px_-6px_rgba(225,29,72,0.7)] active:scale-95">
                    <x-filament::icon :icon="Heroicon::XMark" class="size-4 text-white" />
                    Reject
                </button>
                <button type="button" x-on:click="sheet = 'approve'" class="flex flex-1 items-center justify-center gap-1.5 rounded-full bg-gradient-to-b from-emerald-400 to-emerald-600 px-4 py-2.5 text-xs font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.35),0_10px_24px_-6px_rgba(16,185,129,0.75)] active:scale-95">
                    <x-filament::icon :icon="Heroicon::CheckBadge" class="size-4 text-white" />
                    Approve
                </button>
            </div>
        </div>

        {{-- Decision sheet: a bottom sheet on phones, a dialog on laptops --}}
        <div x-cloak x-show="sheet" class="fixed inset-0 z-50 flex items-end justify-center lg:items-center">
            <div x-show="sheet" x-transition.opacity x-on:click="sheet = null" class="absolute inset-0 bg-slate-900/25 backdrop-blur-[2px]"></div>

            <div
                x-show="sheet"
                x-transition:enter="transition duration-200 ease-out"
                x-transition:enter-start="translate-y-full lg:translate-y-4 lg:opacity-0"
                x-transition:enter-end="translate-y-0 lg:opacity-100"
                role="dialog"
                aria-modal="true"
                class="relative w-full max-w-xl rounded-t-[32px] border-t border-white bg-white/95 p-6 pt-3 shadow-2xl backdrop-blur-2xl lg:max-w-md lg:rounded-[28px] lg:pt-6"
            >
                <span class="mx-auto mb-3 block h-1.5 w-12 rounded-full bg-slate-300 lg:hidden"></span>

                <h2 class="font-display text-lg font-bold text-slate-900">
                    <span x-show="sheet === 'approve'">Approve {{ $peso($totals['gross']) }}?</span>
                    <span x-show="sheet === 'return'">Return to {{ $requestor }}?</span>
                    <span x-show="sheet === 'reject'">Reject this request?</span>
                </h2>
                <p class="mt-1 text-xs text-slate-500">
                    <span x-show="sheet === 'approve'">It moves on to the next approver.</span>
                    <span x-show="sheet === 'return'">They can edit it and send it back.</span>
                    <span x-show="sheet === 'reject'">This is final. The requestor will see your reason.</span>
                </p>

                <label class="mt-4 block">
                    <span class="text-xs font-semibold text-slate-700">
                        Remarks
                        <span x-show="sheet === 'approve'" class="font-normal text-slate-400">(optional)</span>
                    </span>
                    <textarea
                        x-model="remarks"
                        rows="3"
                        x-bind:placeholder="sheet === 'approve' ? 'Add a note for the record' : 'Tell the requestor what to fix'"
                        class="mt-1.5 w-full resize-none rounded-xl border border-slate-200/70 bg-white/70 p-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-3 focus:ring-indigo-500/15 focus:outline-none"
                    ></textarea>
                </label>
                @error('remarks')
                    <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>
                @enderror

                <div class="mt-5 flex items-center gap-3">
                    <button type="button" x-on:click="sheet = null" class="px-2 py-3 text-xs font-semibold text-slate-500 hover:text-slate-800">Cancel</button>
                    <button
                        type="button"
                        x-on:click="$wire.submitDecision(sheet, remarks).then((done) => { if (done) { sheet = null; remarks = '' } })"
                        wire:loading.attr="disabled"
                        x-bind:class="{ 'bg-gradient-to-b from-emerald-500 to-emerald-600 shadow-emerald-600/30': sheet === 'approve', 'bg-orange-600': sheet === 'return', 'bg-rose-600': sheet === 'reject' }"
                        class="flex-1 rounded-2xl px-4 py-3.5 font-display text-sm font-bold text-white shadow-lg transition active:scale-95 disabled:opacity-60"
                    >
                        <span x-show="sheet === 'approve'">Confirm approval</span>
                        <span x-show="sheet === 'return'">Return for revision</span>
                        <span x-show="sheet === 'reject'">Reject request</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- After a decision: full screen on phones, a dialog on laptops --}}
    @if ($outcome)
        @php
            $after = $this->afterDecision();
            $firstName = str($requestor)->before(' ');

            [$heading, $lead, $icon, $ring, $badge, $pill] = match ($outcome['decision']) {
                'approve' => [
                    'RFP approved',
                    $after['step'] < $after['steps'] ? "It's now with the {$after['next']}. We'll let {$firstName} know." : "All approvers signed. It moves on to AP Processing. We'll let {$firstName} know.",
                    Heroicon::Check, 'bg-emerald-200/50', 'bg-emerald-600 shadow-[0_12px_28px_rgba(5,150,105,0.35)]', 'bg-emerald-50 text-emerald-700 ring-emerald-200/70',
                ],
                'return' => [
                    'Returned to '.$firstName,
                    "{$firstName} can fix it and send it back. It will come back to you after AP Review.",
                    Heroicon::ArrowUturnLeft, 'bg-orange-200/50', 'bg-orange-500 shadow-[0_12px_28px_rgba(249,115,22,0.35)]', 'bg-orange-50 text-orange-700 ring-orange-200/70',
                ],
                default => [
                    'RFP rejected',
                    "This request is closed. {$firstName} will see your reason.",
                    Heroicon::XMark, 'bg-rose-200/50', 'bg-rose-600 shadow-[0_12px_28px_rgba(225,29,72,0.35)]', 'bg-rose-50 text-rose-700 ring-rose-200/70',
                ],
            };

            $decisionLabel = ['approve' => 'Approved', 'return' => 'Returned', 'reject' => 'Rejected'][$outcome['decision']];
            $nextApproval = $after['nextApproval'];
        @endphp

        <div class="fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto bg-[#f5f6fb] p-4 lg:bg-slate-900/25 lg:pl-64 lg:backdrop-blur-sm">
            <div class="my-aurora lg:hidden" aria-hidden="true">
                <span class="top-6 left-1/2 size-72 -translate-x-1/2 bg-[#A7F3D0] opacity-40"></span>
                <span class="top-1/3 -right-16 size-64 bg-[#C4B5FD] opacity-40"></span>
                <span class="bottom-10 -left-16 size-64 bg-[#BAE6FD] opacity-40"></span>
            </div>

            <div class="relative flex w-full max-w-md flex-col gap-3">
                <div class="my-glass flex flex-col items-center rounded-[28px] p-6 text-center shadow-xl shadow-indigo-900/5 lg:bg-white/95 lg:p-8">
                    <div class="relative flex size-28 items-center justify-center">
                        <span class="absolute inset-0 animate-pulse rounded-full {{ $ring }}"></span>
                        <span class="absolute inset-3 rounded-full bg-white/80 shadow-sm backdrop-blur-md"></span>
                        <span class="relative flex size-14 items-center justify-center rounded-full text-white {{ $badge }}">
                            <x-filament::icon :icon="$icon" class="size-7" />
                        </span>
                    </div>

                    <h2 class="mt-4 font-display text-2xl font-extrabold tracking-tight text-slate-900">{{ $heading }}</h2>
                    <p class="mt-2 inline-flex items-center gap-2 rounded-full bg-white/80 px-3.5 py-1.5 shadow-sm ring-1 ring-slate-200/60">
                        <span class="font-mono text-xs font-medium text-slate-600">{{ $rfp['number'] }}</span>
                        <span class="size-1 rounded-full bg-slate-300"></span>
                        <span class="font-display text-sm font-bold text-indigo-700 tabular-nums">{{ $peso($totals['gross']) }}</span>
                    </p>
                    <p class="mt-3 max-w-[300px] text-sm leading-relaxed text-slate-500">{{ $lead }}</p>

                    {{-- Where it goes from here --}}
                    <div class="mt-5 w-full rounded-2xl bg-slate-50/80 p-4 text-left ring-1 ring-slate-200/50">
                        <div class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                                <x-filament::icon :icon="Heroicon::OutlinedCheckBadge" class="size-4.5 text-indigo-600" />
                                Approval
                            </span>
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 {{ $pill }}">{{ $decisionLabel }} by you</span>
                        </div>

                        @if ($outcome['decision'] === 'approve')
                            <div class="mt-3 flex gap-1">
                                @foreach (range(1, $after['steps']) as $bar)
                                    <span @class(['h-1.5 flex-1 rounded-full', 'bg-emerald-500' => $bar <= $after['step'], 'bg-slate-200' => $bar > $after['step']])></span>
                                @endforeach
                            </div>
                            <p class="mt-1.5 text-[11px] text-slate-500">Step {{ $after['step'] }} of {{ $after['steps'] }} signed</p>
                        @endif

                        <dl class="mt-3 flex flex-col gap-2 border-t border-slate-200/70 pt-3 text-xs">
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-500">Your decision</dt>
                                <dd class="font-medium text-slate-900">{{ $decisionLabel }} · {{ $outcome['at'] }}</dd>
                            </div>
                            @if ($after['next'])
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-slate-500">{{ $outcome['decision'] === 'return' ? 'Back with' : 'Next' }}</dt>
                                    <dd class="flex items-center gap-1.5 font-semibold text-slate-900">
                                        <span class="size-1.5 rounded-full bg-indigo-500"></span>
                                        {{ $after['next'] }}
                                    </dd>
                                </div>
                            @endif
                            @unless ($outcome['decision'] === 'return')
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-slate-500">Requestor</dt>
                                    <dd class="font-medium text-slate-900">{{ $requestor }}</dd>
                                </div>
                            @endunless
                            @if ($outcome['remarks'])
                                <div class="flex flex-col gap-1">
                                    <dt class="text-slate-500">Your remarks</dt>
                                    <dd class="rounded-xl bg-white/80 px-3 py-2 text-slate-700 italic ring-1 ring-slate-200/60">“{{ $outcome['remarks'] }}”</dd>
                                </div>
                            @endif
                        </dl>
                    </div>

                    <p class="mt-4 rounded-full bg-amber-50 px-3 py-1 text-[11px] font-semibold text-amber-700">Preview only. Nothing was saved yet.</p>
                </div>

                <div class="flex flex-col gap-1">
                    @if ($nextApproval)
                        <a href="{{ RfpDetail::getUrl(['number' => $nextApproval['number']]) }}" class="flex items-center justify-center gap-2 rounded-2xl bg-slate-900 py-3.5 text-sm font-semibold text-white shadow-lg shadow-slate-900/20 transition hover:bg-slate-800 active:scale-[0.99]">
                            Review next · {{ $nextApproval['payee'] }}
                            <x-filament::icon :icon="Heroicon::ArrowRight" class="size-4" />
                        </a>
                        <a href="{{ Approvals::getUrl() }}" class="py-2.5 text-center text-sm font-semibold text-slate-500 hover:text-slate-800">Back to approvals</a>
                    @else
                        <a href="{{ Approvals::getUrl() }}" class="flex items-center justify-center gap-2 rounded-2xl bg-slate-900 py-3.5 text-sm font-semibold text-white shadow-lg shadow-slate-900/20 transition hover:bg-slate-800 active:scale-[0.99]">
                            Back to approvals
                            <x-filament::icon :icon="Heroicon::ArrowRight" class="size-4" />
                        </a>
                        <a href="{{ Dashboard::getUrl() }}" class="py-2.5 text-center text-sm font-semibold text-slate-500 hover:text-slate-800">Back to home</a>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
