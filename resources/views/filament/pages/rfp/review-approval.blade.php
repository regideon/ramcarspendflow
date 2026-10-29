@php
    /**
     * Redesigned RFP approval screen (Google Stitch). Logic is ViewRfpApproval's; see ReviewRfpApproval.
     */
    use App\Enums\RfpRequestType;
    use App\Enums\RfpStatus;
    use App\Filament\Resources\Rfps\Schemas\RfpInfolist;
    use Filament\Forms\Components\RichEditor\RichContentRenderer;
    use App\Filament\Support\CommentMentions;

    $rfp = $this->getRecord();
    $steps = $this->steps();
    $reached = collect($steps)->keys()->filter(fn (int $i): bool => in_array($steps[$i]['state'], ['done', 'skipped'], true))->max() ?? -1;
    $progress = count($steps) > 1 ? max(0, min(100, ($reached + 1) / (count($steps) - 1) * 100)) : 0;
    $decisions = $this->decisionActions();
    $foreign = $this->foreignTotals();
    $taxTotals = $this->hasTaxEntries() ? $this->netOfWithholdingTotals() : [];
    $label = 'text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400';
    $th = 'whitespace-nowrap px-3 py-2.5 text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400';
    $boolPill = fn (bool $value): string => $value
        ? '<span class="inline-flex items-center gap-1 rounded-full bg-success-100 px-2.5 py-0.5 text-xs font-medium text-success-800 dark:bg-success-500/15 dark:text-success-300">✓ Yes</span>'
        : '<span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">✕ No</span>';
@endphp

<x-filament-panels::page>
    <div class="space-y-6 pb-4">
        @if ($rfp->status === RfpStatus::ForRevision)
            <div class="flex gap-3 rounded-xl bg-warning-50 p-4 text-sm text-warning-800 ring-1 ring-warning-200 dark:bg-warning-500/10 dark:text-warning-300 dark:ring-warning-500/30">
                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="size-5 shrink-0" />
                <div>
                    <p class="font-semibold">Returned for revision</p>
                    <p class="mt-0.5">{{ $this->revisionNote() }}</p>
                </div>
            </div>
        @endif

        {{-- Progress tracker --}}
        <section data-tour="progress" class="sf-card p-6">
            <h2 class="font-display text-base font-semibold text-gray-900 dark:text-white">Progress</h2>

            <div class="relative mt-5 overflow-x-auto">
                <div class="relative min-w-[44rem]">
                    <div class="absolute inset-x-[7%] top-5 h-1 rounded-full bg-gray-100 dark:bg-white/10">
                        <div class="h-full rounded-full bg-success-500" style="width: {{ $progress }}%"></div>
                    </div>
                    <ol class="relative grid grid-cols-7 gap-2">
                        @foreach ($steps as $step)
                            <li class="flex flex-col items-center text-center">
                                <span class="relative">
                                    <span @class([
                                        'flex size-10 items-center justify-center rounded-full ring-4 ring-white dark:ring-gray-900',
                                        'bg-success-600 text-white shadow-md' => $step['state'] === 'done',
                                        'bg-primary-600 text-white shadow-lg !ring-primary-100 dark:!ring-primary-500/30' => $step['state'] === 'current',
                                        'bg-warning-400 text-warning-950 shadow-md' => $step['state'] === 'revision',
                                        'bg-danger-600 text-white shadow-md' => $step['state'] === 'failed',
                                        'bg-gray-200 text-gray-500 dark:bg-white/10 dark:text-gray-400' => $step['state'] === 'skipped',
                                        'bg-gray-100 text-gray-400 dark:bg-white/5 dark:text-gray-500' => $step['state'] === 'upcoming',
                                    ])>
                                        <x-filament::icon :icon="$step['icon']" class="size-5" />
                                    </span>
                                    @if ($step['state'] === 'current')
                                        <span class="absolute -end-0.5 -top-0.5 flex size-3.5">
                                            <span class="absolute inline-flex size-full animate-ping rounded-full bg-primary-400 opacity-75"></span>
                                            <span class="relative inline-flex size-3.5 rounded-full bg-primary-600 ring-2 ring-white dark:ring-gray-900"></span>
                                        </span>
                                    @endif
                                </span>
                                <span @class([
                                    'mt-2.5 text-xs',
                                    'font-bold text-primary-700 dark:text-primary-300' => $step['state'] === 'current',
                                    'font-medium text-gray-900 dark:text-white' => in_array($step['state'], ['done', 'revision', 'failed'], true),
                                    'text-gray-500 dark:text-gray-400' => in_array($step['state'], ['upcoming', 'skipped'], true),
                                ])>{{ $step['label'] }}</span>
                                <span @class([
                                    'text-[0.6875rem]',
                                    'font-semibold text-primary-600 dark:text-primary-400' => $step['state'] === 'current',
                                    'font-medium text-success-700 dark:text-success-400' => $step['state'] === 'done',
                                    'text-gray-400' => ! in_array($step['state'], ['current', 'done'], true),
                                ])>{{ $step['who'] }}</span>
                                @if ($step['note'])
                                    <span @class([
                                        'mt-1 rounded-full px-2 py-0.5 text-[0.6875rem] font-semibold',
                                        'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' => $step['state'] === 'current',
                                        'bg-warning-50 text-warning-800 dark:bg-warning-500/10 dark:text-warning-300' => $step['state'] === 'revision',
                                        'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-300' => $step['state'] === 'failed',
                                        'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => $step['state'] === 'skipped',
                                    ])>{{ $step['note'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </section>

        {{-- Summary --}}
        <section data-tour="summary" class="sf-card p-6">
            @include('filament.pages.rfp.review-approval.card-title', ['icon' => 'heroicon-o-clipboard-document-list', 'title' => 'Request overview'])

            <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-5 lg:grid-cols-4">
                <div class="space-y-1"><dt class="{{ $label }}">Status</dt><dd>@include('filament.pages.rfp.review-approval.status-badge', ['status' => $rfp->status])</dd></div>
                <div class="space-y-1">
                    <dt class="{{ $label }}">Due type</dt>
                    <dd>
                        @if ($rfp->dueType?->code === 'urgent')
                            <span class="inline-flex items-center gap-1 rounded-full bg-danger-100 px-2.5 py-0.5 text-xs font-semibold text-danger-700 dark:bg-danger-500/15 dark:text-danger-300"><span class="size-1.5 rounded-full bg-danger-500"></span>{{ $rfp->dueType->name }}</span>
                        @else
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $rfp->dueType?->name ?? '—' }}</span>
                        @endif
                    </dd>
                </div>
                <div class="space-y-1">
                    <dt class="{{ $label }}">Due date</dt>
                    <dd @class(['flex items-center gap-1.5 text-sm font-semibold', 'text-danger-600 dark:text-danger-400' => $rfp->dueState() === 'overdue', 'text-warning-600 dark:text-warning-400' => $rfp->dueState() === 'today', 'text-gray-900 dark:text-white' => ! $rfp->dueState()])>
                        <x-filament::icon icon="heroicon-m-calendar" class="size-4 text-primary-600" />
                        {{ $rfp->due_date?->format('M j, Y') ?? '—' }}
                    </dd>
                </div>
                <div class="space-y-1"><dt class="{{ $label }}">RFP type</dt><dd><span class="rounded-md bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-white/10 dark:text-gray-200">{{ $rfp->request_type?->getLabel() ?? '—' }}</span></dd></div>

                @if (in_array($rfp->status, [RfpStatus::ForTreasury, RfpStatus::Completed], true))
                    <div class="space-y-1"><dt class="{{ $label }}">Payment type</dt><dd><span class="rounded-md bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-white/10 dark:text-gray-200">{{ $rfp->is_ids ? 'IDS' : 'Non-IDS (manual)' }}</span></dd></div>
                @endif
                <div class="space-y-1"><dt class="{{ $label }}">Paying company</dt><dd class="text-sm font-semibold text-gray-900 dark:text-white">{{ $rfp->company?->name ?? '—' }}</dd></div>
                <div class="space-y-1"><dt class="{{ $label }}">Account group</dt><dd class="text-sm font-medium text-gray-900 dark:text-white">{{ $rfp->accountGroup?->description ?? '—' }}</dd></div>
                <div class="space-y-1"><dt class="{{ $label }}">{{ $rfp->request_type === RfpRequestType::Reimbursement ? 'Final payee' : 'Payee' }}</dt><dd class="text-sm font-semibold text-gray-900 dark:text-white">{{ $rfp->vendor?->name ?? '—' }}</dd></div>
                <div class="space-y-1"><dt class="{{ $label }}">Payment method</dt><dd class="text-sm font-medium text-gray-900 dark:text-white">{{ $rfp->paymentMethod?->description ?? '—' }}</dd></div>
                <div class="space-y-1"><dt class="{{ $label }}">Direct to VP</dt><dd>{!! $boolPill((bool) $rfp->is_direct_to_vp) !!}</dd></div>
                <div class="space-y-1"><dt class="{{ $label }}">Pre-approved</dt><dd>{!! $boolPill((bool) $rfp->is_pre_approved) !!}</dd></div>
                <div class="space-y-1 rounded-xl bg-primary-50/60 p-3 dark:bg-primary-500/10">
                    <dt class="text-[0.6875rem] font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-300">Total amount</dt>
                    <dd class="font-display text-2xl font-bold tabular-nums text-gray-900 dark:text-white">PHP {{ number_format((float) $rfp->total_php_amount, 2) }}</dd>
                    <dd class="text-[0.6875rem] text-gray-500 dark:text-gray-400">Foreign currency: {{ $foreign ? implode(' · ', $foreign) : '—' }}</dd>
                </div>

                <div class="col-span-full space-y-1">
                    <dt class="{{ $label }}">Approval route</dt>
                    <dd class="flex flex-wrap items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900 dark:bg-white/5 dark:text-white">
                        @foreach (explode(' → ', $this->approvalRoute()) as $approver)
                            @unless ($loop->first)
                                <x-filament::icon icon="heroicon-m-arrow-right" class="size-4 text-gray-400" />
                            @endunless
                            <span>{{ $approver }}</span>
                        @endforeach
                    </dd>
                </div>
                <div class="col-span-full space-y-1">
                    <dt class="{{ $label }}">Purpose</dt>
                    <dd class="whitespace-pre-line rounded-lg bg-gray-50 p-3 text-sm text-gray-900 dark:bg-white/5 dark:text-white">{{ $rfp->purpose ?: '—' }}</dd>
                </div>
                @if ($rfp->is_pre_approved)
                    <div class="col-span-full space-y-1">
                        <dt class="{{ $label }}">Proof of approval</dt>
                        <dd class="flex flex-wrap gap-2 text-sm">
                            @forelse ($rfp->pre_approval_documents ?? [] as $path)
                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-gray-100 px-2.5 py-1 text-xs dark:bg-white/10"><x-filament::icon icon="heroicon-m-paper-clip" class="size-3.5 text-primary-600" />{{ RfpInfolist::fileLink($path) }}</span>
                            @empty
                                <span class="text-gray-500">None</span>
                            @endforelse
                        </dd>
                    </div>
                @endif
            </dl>
        </section>

        {{-- Items --}}
        <section data-tour="items" class="sf-card p-6">
            <div class="flex items-center justify-between gap-3">
                @include('filament.pages.rfp.review-approval.card-title', ['icon' => 'heroicon-o-queue-list', 'title' => 'Items'])
                <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $rfp->items->count() }} {{ str('line')->plural($rfp->items->count()) }}</span>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[60rem] text-start text-sm">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="{{ $th }} rounded-s-lg text-start">Item details</th>
                            <th class="{{ $th }} text-start">Cost center</th>
                            <th class="{{ $th }} text-start">PO no.</th>
                            <th class="{{ $th }} text-start">Sub category</th>
                            <th class="{{ $th }} text-start">Invoice</th>
                            <th class="{{ $th }} text-start">Paid to</th>
                            <th class="{{ $th }} text-end">Amount</th>
                            <th class="{{ $th }} text-end">PHP</th>
                            <th class="{{ $th }} rounded-e-lg text-start">Documents</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-900 dark:divide-white/5 dark:text-white">
                        @forelse ($rfp->items as $item)
                            <tr class="align-top hover:bg-gray-50/60 dark:hover:bg-white/[0.02]">
                                <td class="px-3 py-3.5 font-medium">{{ $item->description }}</td>
                                <td class="px-3 py-3.5">
                                    @if ($item->costCenter)
                                        <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold dark:bg-white/10">{{ $item->costCenter->code }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3.5">{{ $item->po_number ?: '—' }}</td>
                                <td class="px-3 py-3.5 text-gray-600 dark:text-gray-300">
                                    {{ $item->subcategory?->name ?? '—' }}
                                    @if ($item->glAccount)
                                        <div class="text-[0.6875rem] text-gray-400">{{ $item->glAccount->code }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3.5">
                                    <span class="font-semibold text-primary-700 dark:text-primary-300">{{ $item->invoice_number ?: '—' }}</span>
                                    @if ($item->invoice_date)
                                        <div class="text-[0.6875rem] text-gray-400">{{ $item->invoice_date->format('M j, Y') }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3.5 font-medium">{{ $item->paidToName() ?? '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-3.5 text-end tabular-nums">{{ $item->currency?->code }} {{ number_format((float) $item->amount, 2) }}</td>
                                <td class="whitespace-nowrap px-3 py-3.5 text-end font-bold tabular-nums">{{ number_format((float) $item->php_amount, 2) }}</td>
                                <td class="px-3 py-3.5">
                                    <div class="flex flex-col items-start gap-1">
                                        @forelse ($item->attachments ?? [] as $path)
                                            <span class="inline-flex max-w-48 items-center gap-1.5 truncate rounded-lg bg-gray-100 px-2.5 py-1 text-xs dark:bg-white/10">
                                                <x-filament::icon icon="heroicon-m-paper-clip" class="size-3.5 shrink-0 text-primary-600" />
                                                {{ RfpInfolist::fileLink($path) }}
                                            </span>
                                        @empty
                                            <span class="text-xs text-gray-400">None</span>
                                        @endforelse
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-3 py-8 text-center text-sm text-gray-500">No items</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Tax entries: once AP has processed the tax codes --}}
        @if ($this->hasTaxEntries())
            <section class="sf-card p-6">
                @include('filament.pages.rfp.review-approval.card-title', ['icon' => 'heroicon-o-receipt-percent', 'title' => 'Tax entries', 'subtitle' => "Booked by AP at processing. Amounts are in each line's currency."])

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[56rem] text-start text-sm">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="{{ $th }} rounded-s-lg text-start">Invoice</th>
                                <th class="{{ $th }} text-end">Gross</th>
                                <th class="{{ $th }} text-start">Input tax</th>
                                <th class="{{ $th }} text-start">Withholding tax</th>
                                <th class="{{ $th }} text-end">VAT</th>
                                <th class="{{ $th }} text-end">Net of VAT</th>
                                <th class="{{ $th }} text-end">Withholding</th>
                                <th class="{{ $th }} rounded-e-lg text-end">Net of WHT</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-900 dark:divide-white/5 dark:text-white">
                            @foreach ($rfp->items as $item)
                                @php $money = fn ($value): string => $value === null ? '—' : number_format((float) $value, 2); @endphp
                                <tr class="align-top">
                                    <td class="px-3 py-3.5 font-semibold text-primary-700 dark:text-primary-300">{{ $item->invoice_number ?: '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-3.5 text-end tabular-nums">{{ $item->currency?->code }} {{ number_format((float) $item->amount, 2) }}</td>
                                    <td class="px-3 py-3.5">
                                        @if ($item->taxCode)
                                            <span class="rounded-md bg-primary-50 px-2 py-0.5 text-xs font-semibold text-primary-800 dark:bg-primary-500/10 dark:text-primary-300">{{ $item->taxCode->code }}</span>
                                            <div class="mt-1 text-[0.6875rem] text-gray-500 dark:text-gray-400">{{ $item->taxCode->description }}</div>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3.5">
                                        @if ($item->withholdingTaxCode)
                                            <span class="rounded-md bg-warning-50 px-2 py-0.5 text-xs font-semibold text-warning-800 dark:bg-warning-500/10 dark:text-warning-300">{{ $item->withholdingTaxCode->code }}</span>
                                            <div class="mt-1 text-[0.6875rem] text-gray-500 dark:text-gray-400">{{ $item->withholdingTaxCode->description }}{{ $item->withholdingTaxType ? ' · '.$item->withholdingTaxType->code : '' }}</div>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3.5 text-end tabular-nums">{{ $money($item->vat_amount) }}</td>
                                    <td class="px-3 py-3.5 text-end tabular-nums">{{ $money($item->net_of_vat_amount) }}</td>
                                    <td class="px-3 py-3.5 text-end tabular-nums text-danger-600 dark:text-danger-400">{{ $money($item->withholding_tax_amount) }}</td>
                                    <td class="px-3 py-3.5 text-end font-bold tabular-nums">{{ $money($item->net_of_withholding_tax_amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-wrap items-baseline justify-end gap-x-3 gap-y-1 rounded-xl bg-gray-50 px-4 py-3 dark:bg-white/5">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total net of withholding tax</span>
                    @foreach ($taxTotals as $code => $total)
                        <span class="font-display text-lg font-bold tabular-nums text-primary-700 dark:text-primary-300">{{ $code }} {{ $total }}</span>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- History and comments --}}
        <div data-tour="history" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <section class="sf-card p-6">
                <div class="flex items-center justify-between gap-3">
                    @include('filament.pages.rfp.review-approval.card-title', ['icon' => 'heroicon-o-clock', 'title' => 'History'])
                    <span class="text-[0.6875rem] text-gray-400">{{ $rfp->histories->count() }} {{ str('entry')->plural($rfp->histories->count()) }}</span>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-start text-xs">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="{{ $th }} rounded-s-lg text-start">When</th>
                                <th class="{{ $th }} text-start">By</th>
                                <th class="{{ $th }} text-start">Action</th>
                                <th class="{{ $th }} text-start">Status</th>
                                <th class="{{ $th }} rounded-e-lg text-start">Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-900 dark:divide-white/5 dark:text-white">
                            @forelse ($rfp->histories->sortByDesc('id') as $history)
                                <tr class="align-top">
                                    <td class="whitespace-nowrap px-3 py-3 text-gray-500 dark:text-gray-400">{{ $history->created_at->format('M j, Y') }}<br><span class="text-gray-400">{{ $history->created_at->format('g:i A') }}</span></td>
                                    <td class="px-3 py-3 font-medium">{{ $history->user?->name ?? 'System' }}</td>
                                    <td class="px-3 py-3">{{ $history->action ?: '—' }}</td>
                                    <td class="px-3 py-3">
                                        @if ($history->to_status)
                                            @include('filament.pages.rfp.review-approval.status-badge', ['status' => $history->to_status])
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-gray-600 dark:text-gray-300">{{ $history->remarks ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-8 text-center text-gray-500">No history yet</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="sf-card flex flex-col p-6">
                <div class="flex items-start justify-between gap-3">
                    @include('filament.pages.rfp.review-approval.card-title', ['icon' => 'heroicon-o-chat-bubble-left-ellipsis', 'title' => 'Comments', 'subtitle' => 'Questions and answers on this RFP. Posting a comment does not change the status.'])
                    {{ $this->addCommentAction }}
                </div>

                <div class="mt-4 flex-1 space-y-3">
                    @forelse ($rfp->comments as $comment)
                        <article class="space-y-2 rounded-xl bg-gray-50 p-3.5 dark:bg-white/5">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="flex size-7 items-center justify-center rounded-full bg-primary-600 text-[0.6875rem] font-bold text-white">{{ str($comment->user?->name ?? '?')->explode(' ')->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('') }}</span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $comment->user?->name ?? 'Someone' }}</span>
                                </div>
                                <span class="text-[0.6875rem] text-gray-400">{{ $comment->created_at->format('M j, Y · g:i A') }}</span>
                            </div>
                            <div class="prose prose-sm max-w-none ps-9 dark:prose-invert">
                                {!! RichContentRenderer::make($comment->body)->mentions([CommentMentions::provider()])->toHtml() !!}
                            </div>
                            @if (filled($comment->attachments))
                                <div class="flex flex-wrap gap-2 ps-9">
                                    @foreach ($comment->attachments as $path)
                                        <span class="inline-flex items-center gap-1.5 rounded bg-white px-2.5 py-1 text-xs shadow-sm dark:bg-gray-800">
                                            <x-filament::icon icon="heroicon-m-paper-clip" class="size-3.5 text-primary-600" />
                                            {{ RfpInfolist::fileLink($path) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </article>
                    @empty
                        <p class="py-8 text-center text-sm text-gray-500">No comments yet</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    {{-- Sticky decision bar: Return / Cancel on the left, the decision on the right --}}
    @if ($decisions['primary'] || $decisions['secondary'])
        <div class="sf-ap-actionbar" data-tour="actions">
            <div class="flex w-full flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($decisions['secondary'] as $action)
                        {{ $action }}
                    @endforeach
                </div>
                <div class="flex items-center gap-4">
                    <div class="hidden flex-col text-end leading-tight md:flex">
                        <span class="text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total amount</span>
                        <span class="font-display text-base font-bold tabular-nums text-gray-900 dark:text-white">PHP {{ number_format((float) $rfp->total_php_amount, 2) }}</span>
                    </div>
                    @foreach ($decisions['primary'] as $action)
                        {{ $action }}
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <div data-tour="actions" class="sf-card flex items-center gap-2 px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
            <x-filament::icon icon="heroicon-m-eye" class="size-4" />
            View only: there is no action for you on this RFP right now.
        </div>
    @endif

    <x-user-guide :steps="$this->guideSteps()" />
</x-filament-panels::page>
