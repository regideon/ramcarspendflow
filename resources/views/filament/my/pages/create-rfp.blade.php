@php
    use App\Filament\My\Pages\CreateRfp;
    use App\Filament\My\Pages\Dashboard;
    use App\Filament\My\Pages\MyRequests;
    use Filament\Support\Icons\Heroicon;
    use Illuminate\Support\HtmlString;
    use Illuminate\Support\Js;

    $options = $this->options();
    $dueDates = $this->dueDates();
    $routes = $this->approvalRoutes();
    $isReimbursement = $this->isReimbursement();
    $user = filament()->auth()->user();

    $peso = fn (float $amount): string => '₱'.number_format($amount, 2);
    $input = 'w-full rounded-xl border border-white/90 bg-white/80 px-3.5 py-2.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-3 focus:ring-indigo-500/15 focus:outline-none';
    $segmented = 'flex items-center gap-1 rounded-xl bg-indigo-100/50 p-1';
    $segment = 'flex flex-1 cursor-pointer items-center justify-center rounded-lg px-2 py-2 text-center text-xs transition';
    $segmentOn = 'bg-white font-semibold text-indigo-700 shadow-sm';
    $segmentOff = 'font-medium text-slate-500 hover:text-slate-800';

    // Classes for a radio-style choice, rendered for the current value and kept in sync by Alpine
    $choice = function (string $path, string $value, string $base, string $on, string $off): HtmlString {
        $expression = '$wire.'.preg_replace('/\.(\d+)/', '[$1]', $path);

        return new HtmlString(sprintf(
            'class="%s %s" x-bind:class="{ %s: %s === %s, %s: %s !== %s }"',
            $base,
            data_get($this, $path) === $value ? $on : $off,
            Js::from($on), $expression, Js::from($value),
            Js::from($off), $expression, Js::from($value),
        ));
    };

    $fileIcon = fn (?string $name): Heroicon => match (strtolower(pathinfo((string) $name, PATHINFO_EXTENSION))) {
        'jpg', 'jpeg', 'png' => Heroicon::OutlinedPhoto,
        'xls', 'xlsx', 'csv' => Heroicon::OutlinedTableCells,
        default => Heroicon::OutlinedDocumentText,
    };
    $fileSize = fn ($file): string => $file->getSize() >= 1048576 ? number_format($file->getSize() / 1048576, 1).' MB' : max(1, round($file->getSize() / 1024)).' KB';
@endphp

<div
    x-data="{
        step: 1,
        openItem: 0,
        confirm: false,
        submitting: false,
        dueDates: @js($dueDates),
        routes: @js($routes),
        get isReimbursement() { return this.$wire.form.requestType === 'reimbursement' },
        get route() { return this.$wire.form.preApproved ? this.routes.pre : (this.$wire.form.directToVp ? this.routes.vp : this.routes.matrix) },
        get payee() { return this.isReimbursement ? 'You (reimbursement)' : (this.$wire.form.vendor || 'Not chosen yet') },
        get total() { return this.$wire.items.reduce((sum, item) => sum + (parseFloat(String(item.amount ?? '').replace(/,/g, '')) || 0), 0) },
        peso(amount) { return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount) },
        async next() {
            if (await this.$wire.validateDetails()) {
                this.step = 2
                window.scrollTo({ top: 0 })
            }
        },
        async submit() {
            this.submitting = true
            const result = await this.$wire.submit()
            this.submitting = false
            this.confirm = false

            if (result && ! result.ok) {
                this.step = result.step
                if (result.item !== null) this.openItem = result.item
                window.scrollTo({ top: 0 })
            }
        },
    }"
    @keydown.escape.window="confirm = false"
    class="flex flex-col gap-4 lg:gap-6"
>
    {{-- Mobile header: close, title, 2-step progress --}}
    <header class="sticky top-0 z-30 -mx-4 -mt-4 flex flex-col gap-2.5 bg-[#f5f6fb]/85 px-4 pt-3 pb-3 backdrop-blur-xl lg:hidden">
        <div class="flex items-center justify-between">
            <a href="{{ Dashboard::getUrl() }}" aria-label="Close" class="my-glass flex size-10 items-center justify-center rounded-full text-slate-700 transition active:scale-95">
                <x-filament::icon :icon="Heroicon::XMark" class="size-5" />
            </a>
            <h1 class="font-display text-lg font-bold tracking-tight text-slate-900">New RFP</h1>
            <span class="size-10"></span>
        </div>
        <div class="flex items-center justify-between text-[11px] font-semibold">
            <span class="tracking-wider text-indigo-600 uppercase" x-text="`Step ${step} of 2`">Step 1 of 2</span>
            <span class="text-slate-500" x-text="step === 1 ? 'Request details' : 'Items & submit'">Request details</span>
        </div>
        <div class="grid grid-cols-2 gap-1.5">
            <span class="h-1.5 rounded-full bg-indigo-600"></span>
            <span class="h-1.5 rounded-full bg-indigo-100 transition" x-bind:class="step === 2 ? '!bg-indigo-600' : ''"></span>
        </div>
    </header>

    {{-- Laptop header --}}
    <header class="hidden items-end justify-between gap-4 lg:flex">
        <div>
            <nav class="flex items-center gap-2 text-xs">
                <a href="{{ MyRequests::getUrl() }}" class="text-slate-500 transition hover:text-indigo-600">Requests</a>
                <span class="text-slate-300">/</span>
                <span class="font-semibold text-slate-700">New RFP</span>
            </nav>
            <div class="mt-1 flex items-center gap-4">
                <h1 class="font-display text-2xl font-extrabold tracking-tight text-slate-900">New RFP</h1>
                <span class="my-glass flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold">
                    <span class="flex items-center gap-1 text-slate-700"><span class="flex size-4 items-center justify-center rounded-full bg-indigo-600 text-[9px] text-white">1</span> Request details</span>
                    <span class="h-px w-4 bg-slate-300"></span>
                    <span class="flex items-center gap-1 text-slate-700"><span class="flex size-4 items-center justify-center rounded-full bg-indigo-600 text-[9px] text-white">2</span> Items &amp; submit</span>
                </span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ Dashboard::getUrl() }}" class="rounded-xl px-4 py-2 text-xs font-semibold text-slate-500 transition hover:text-slate-900">Cancel</a>
            <button type="button" wire:click="saveDraft" class="my-glass flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-white">
                <x-filament::icon :icon="Heroicon::OutlinedBookmark" class="size-4 text-slate-400" />
                Save draft
            </button>
        </div>
    </header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-start lg:gap-6">
        <div class="flex flex-col gap-4 lg:col-span-8 lg:gap-6">
            {{-- Step 1: request details --}}
            <div class="flex flex-col gap-4 lg:gap-6" x-bind:class="{ 'max-lg:hidden': step !== 1 }">
                {{-- Request --}}
                <section class="my-glass flex flex-col gap-4 rounded-[20px] p-4 lg:p-6">
                    <h2 class="flex items-center gap-2 font-display text-base font-bold text-slate-900 lg:text-lg">
                        <x-filament::icon :icon="Heroicon::OutlinedClipboardDocumentList" class="size-5 text-indigo-600" />
                        Request
                    </h2>

                    <x-my.field label="RFP type" error="form.requestType">
                        <div class="grid grid-cols-2 gap-2 lg:gap-3">
                            @foreach (['standard' => ['Standard', 'Pay a vendor or supplier', Heroicon::OutlinedBuildingOffice2], 'reimbursement' => ['Reimbursement', 'Get paid back for something you paid for', Heroicon::OutlinedWallet]] as $value => [$label, $description, $icon])
                                <label {{ $choice('form.requestType', $value, 'relative flex cursor-pointer flex-col gap-2 rounded-2xl border p-3 transition lg:flex-row lg:items-start lg:gap-3 lg:p-4', 'border-indigo-200 bg-indigo-50/80 shadow-sm ring-2 ring-indigo-500/20', 'border-white/90 bg-white/60 hover:bg-white/90') }}>
                                    <input type="radio" wire:model.live="form.requestType" value="{{ $value }}" class="sr-only" />
                                    <span {{ $choice('form.requestType', $value, 'flex size-9 shrink-0 items-center justify-center rounded-xl', 'bg-indigo-600 text-white shadow-[0_2px_8px_rgba(79,70,229,0.3)]', 'bg-slate-100 text-slate-500') }}>
                                        <x-filament::icon :icon="$icon" class="size-5" />
                                    </span>
                                    <span class="flex flex-col">
                                        <span class="text-sm font-semibold text-slate-900">{{ $label }}</span>
                                        <span class="text-[11px] leading-snug text-slate-500">{{ $description }}</span>
                                    </span>
                                    <x-filament::icon :icon="Heroicon::CheckCircle" class="absolute top-3 right-3 size-5 text-indigo-600" x-show="$wire.form.requestType === '{{ $value }}'" :style="$this->form['requestType'] !== $value ? 'display: none' : null" />
                                </label>
                            @endforeach
                        </div>
                    </x-my.field>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <x-my.field label="Due type" error="form.dueType">
                            <div class="{{ $segmented }}">
                                @foreach (CreateRfp::DUE_TYPES as $value => $dueType)
                                    <label {{ $choice('form.dueType', $value, $segment, $segmentOn, $segmentOff) }}>
                                        <input type="radio" wire:model="form.dueType" value="{{ $value }}" class="sr-only" />
                                        {{ $dueType['label'] }} · {{ $dueType['days'] }}-day
                                    </label>
                                @endforeach
                            </div>
                            <span class="flex items-center gap-1.5 text-[11px] text-slate-500">
                                <x-filament::icon :icon="Heroicon::OutlinedCalendarDays" class="size-3.5 text-emerald-600" />
                                Due <strong class="font-semibold text-slate-800" x-text="dueDates[$wire.form.dueType]">{{ $dueDates[$this->form['dueType']] }}</strong>
                            </span>
                        </x-my.field>

                        <x-my.field label="Paying company" error="form.company">
                            <x-my.select :options="$options['companies']" wire:model="form.company" />
                        </x-my.field>
                    </div>
                </section>

                {{-- Payee --}}
                <section class="my-glass flex flex-col gap-4 rounded-[20px] p-4 lg:p-6">
                    <h2 class="flex items-center gap-2 font-display text-base font-bold text-slate-900 lg:text-lg">
                        <x-filament::icon :icon="Heroicon::OutlinedIdentification" class="size-5 text-indigo-600" />
                        Payee
                    </h2>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2" x-show="! isReimbursement" @style(['display: none' => $isReimbursement])>
                        <x-my.field label="Account group" error="form.accountGroup">
                            <x-my.select :options="$options['accountGroups']" wire:model="form.accountGroup" />
                        </x-my.field>

                        <x-my.field label="Vendor" error="form.vendor">
                            <x-my.select :options="$options['vendors']" placeholder="Choose a vendor" wire:model="form.vendor" />
                        </x-my.field>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl bg-indigo-50/70 p-3" x-show="isReimbursement" @style(['display: none' => ! $isReimbursement])>
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-tr from-indigo-600 to-violet-500 text-[11px] font-bold text-white">
                            {{ str($user->name)->explode(' ')->map(fn (string $part): string => mb_substr($part, 0, 1))->take(2)->join('') }}
                        </span>
                        <span class="flex flex-col">
                            <span class="text-sm font-semibold text-slate-900">Paid back to you</span>
                            <span class="text-[11px] text-slate-500">{{ $user->name }} · Employee Account</span>
                        </span>
                    </div>

                    <x-my.field label="Payment method" error="form.paymentMethod" class="lg:w-1/2 lg:pr-2">
                        <x-my.select :options="$options['paymentMethods']" wire:model="form.paymentMethod" />
                    </x-my.field>
                </section>

                {{-- Purpose --}}
                <section class="my-glass flex flex-col gap-4 rounded-[20px] p-4 lg:p-6">
                    <h2 class="flex items-center gap-2 font-display text-base font-bold text-slate-900 lg:text-lg">
                        <x-filament::icon :icon="Heroicon::OutlinedPencilSquare" class="size-5 text-indigo-600" />
                        Purpose
                    </h2>

                    <x-my.field label="What is this payment for?" error="form.purpose">
                        <textarea wire:model="form.purpose" rows="2" maxlength="255" placeholder="e.g. Office supplies – October" class="{{ $input }} resize-none"></textarea>
                    </x-my.field>

                    <div class="grid grid-cols-1 gap-2 lg:grid-cols-2 lg:gap-3">
                        @foreach (['directToVp' => ['Direct to VP', 'Only for RFPs that need VP approval only.', 'preApproved'], 'preApproved' => ['Pre-approved', 'Already approved outside the portal. Attach the proof of approval.', 'directToVp']] as $key => [$label, $hint, $other])
                            <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl bg-white/60 p-3">
                                <span class="flex flex-col">
                                    <span class="text-sm font-semibold text-slate-900">{{ $label }}</span>
                                    <span class="text-[11px] leading-snug text-slate-500">{{ $hint }}</span>
                                </span>
                                <input type="checkbox" wire:model="form.{{ $key }}" x-on:change="if ($event.target.checked) $wire.set('form.{{ $other }}', false)" class="peer sr-only" />
                                <span class="relative h-6 w-11 shrink-0 rounded-full bg-slate-300 transition peer-checked:bg-indigo-600 peer-focus-visible:ring-3 peer-focus-visible:ring-indigo-500/30 after:absolute after:top-0.5 after:left-0.5 after:size-5 after:rounded-full after:bg-white after:shadow-sm after:transition peer-checked:after:translate-x-5"></span>
                            </label>
                        @endforeach
                    </div>

                    <x-my.field label="Proof of approval" error="proof" hint="The approval email or other proof. PDF or image, up to 10 MB." x-show="$wire.form.preApproved" :style="$this->form['preApproved'] ? null : 'display: none'">
                        @if ($proof)
                            <span class="flex items-center justify-between gap-3 rounded-xl bg-white/80 p-2.5 shadow-sm">
                                <span class="flex min-w-0 items-center gap-2.5">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                                        <x-filament::icon :icon="$fileIcon($proof->getClientOriginalName())" class="size-5" />
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-semibold text-slate-900">{{ $proof->getClientOriginalName() }}</span>
                                        <span class="block text-[11px] text-slate-500">{{ $fileSize($proof) }}</span>
                                    </span>
                                </span>
                                <button type="button" wire:click="$set('proof', null)" aria-label="Remove file" class="flex size-8 items-center justify-center rounded-full text-slate-400 transition hover:bg-rose-50 hover:text-rose-600">
                                    <x-filament::icon :icon="Heroicon::XMark" class="size-4" />
                                </button>
                            </span>
                        @else
                            <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-indigo-200 bg-white/50 px-4 py-4 text-sm font-semibold text-indigo-600 transition hover:bg-white/80">
                                <input type="file" wire:model="proof" accept=".pdf,.jpg,.jpeg,.png" class="sr-only" />
                                <x-filament::icon :icon="Heroicon::OutlinedArrowUpTray" class="size-5" />
                                <span wire:loading.remove wire:target="proof">Upload proof of approval</span>
                                <span wire:loading wire:target="proof">Uploading…</span>
                            </label>
                        @endif
                    </x-my.field>
                </section>

                {{-- Phones: step 1 actions --}}
                <div class="fixed inset-x-0 bottom-0 z-40 border-t border-white/80 bg-white/85 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur-2xl lg:hidden" x-show="step === 1">
                    <div class="mx-auto flex max-w-xl items-center gap-3">
                        <button type="button" wire:click="saveDraft" class="px-2 py-3 text-sm font-semibold text-slate-600">Save draft</button>
                        <button type="button" x-on:click="next()" wire:loading.attr="disabled" wire:target="validateDetails" class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-indigo-600 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/25 transition active:scale-[0.98] disabled:opacity-60">
                            Continue to items
                            <x-filament::icon :icon="Heroicon::ArrowRight" class="size-4" />
                        </button>
                    </div>
                </div>
            </div>

            {{-- Step 2: items --}}
            <div class="flex flex-col gap-4 max-lg:hidden lg:gap-6" x-bind:class="{ 'max-lg:hidden': step !== 2 }">
                {{-- Phones: what step 1 said --}}
                <div class="my-glass flex items-center justify-between gap-3 rounded-2xl px-4 py-3 lg:hidden">
                    <p class="flex min-w-0 items-center gap-2 text-xs text-slate-700">
                        <x-filament::icon :icon="Heroicon::OutlinedCheckBadge" class="size-4 shrink-0 text-indigo-600" />
                        <span class="truncate" x-text="`${isReimbursement ? 'Reimbursement' : 'Standard'} · ${payee} · Due ${dueDates[$wire.form.dueType]}`"></span>
                    </p>
                    <button type="button" x-on:click="step = 1; window.scrollTo({ top: 0 })" class="shrink-0 text-xs font-semibold text-indigo-600">Edit</button>
                </div>

                <section class="my-glass flex flex-col gap-3 rounded-[20px] p-4 lg:p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="flex items-center gap-2 font-display text-base font-bold text-slate-900 lg:text-lg">
                            <x-filament::icon :icon="Heroicon::OutlinedListBullet" class="size-5 text-indigo-600" />
                            Items ({{ count($items) }})
                        </h2>
                        <button type="button" x-on:click="$wire.addItem().then((index) => openItem = index)" class="hidden items-center gap-1.5 rounded-xl bg-white/80 px-3 py-1.5 text-xs font-semibold text-indigo-600 shadow-sm transition hover:bg-white lg:flex">
                            <x-filament::icon :icon="Heroicon::Plus" class="size-4" />
                            Add item
                        </button>
                    </div>

                    {{-- Laptop column headings --}}
                    <div class="hidden grid-cols-[2.25rem_minmax(0,1fr)_7.5rem_6.5rem_7rem_1rem] gap-3 rounded-xl bg-white/40 px-3 py-2.5 font-mono text-[10px] font-semibold tracking-wider text-slate-400 uppercase lg:grid">
                        <span>Doc</span>
                        <span>Item details</span>
                        <span>Cost center / PO</span>
                        <span>Invoice</span>
                        <span class="text-right">Amount</span>
                        <span></span>
                    </div>

                    @foreach ($items as $index => $item)
                        @php
                            $hasErrors = collect($errors->keys())->contains(fn (string $key): bool => str_starts_with($key, "items.{$index}."));
                            $wireItem = "\$wire.items[{$index}]";
                        @endphp

                        <div wire:key="item-{{ $index }}" @class(['overflow-hidden rounded-2xl border bg-white/60 transition', 'border-rose-200' => $hasErrors, 'border-white/90' => ! $hasErrors]) x-bind:class="openItem === {{ $index }} ? 'bg-white/90 shadow-md' : ''">
                            {{-- Summary row: a card line on phones, a table row on laptops --}}
                            <button type="button" x-on:click="openItem = openItem === {{ $index }} ? null : {{ $index }}" class="flex w-full items-center gap-3 px-3 py-3 text-left lg:grid lg:grid-cols-[2.25rem_minmax(0,1fr)_7.5rem_6.5rem_7rem_1rem]">
                                <span @class(['flex size-9 shrink-0 items-center justify-center rounded-lg', 'bg-indigo-50 text-indigo-600' => $item['upload'], 'bg-slate-100 text-slate-400' => ! $item['upload']])>
                                    <x-filament::icon :icon="$item['upload'] ? $fileIcon($item['upload']->getClientOriginalName()) : Heroicon::OutlinedPaperClip" class="size-4" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-slate-900" x-text="{{ $wireItem }}.description || 'New item'">{{ $item['description'] ?: 'New item' }}</span>
                                    <span class="block truncate text-[11px] text-slate-500 lg:hidden">
                                        @if ($hasErrors)
                                            <span class="font-semibold text-rose-600">Needs your attention</span>
                                        @else
                                            <span x-text="{{ $wireItem }}.invoiceNo ? `Invoice ${ {{ $wireItem }}.invoiceNo }` : 'No invoice yet'">{{ $item['invoiceNo'] ? 'Invoice '.$item['invoiceNo'] : 'No invoice yet' }}</span>
                                        @endif
                                    </span>
                                    @if ($hasErrors)
                                        <span class="hidden text-[11px] font-semibold text-rose-600 lg:block">Needs your attention</span>
                                    @else
                                        <span class="hidden truncate text-[11px] text-slate-500 lg:block">{{ $item['subcategory'] ?: 'No sub category yet' }}</span>
                                    @endif
                                </span>
                                <span class="hidden truncate font-mono text-[11px] text-slate-600 lg:block" x-text="{{ $wireItem }}.chargeTo === 'po' ? ({{ $wireItem }}.po || '—') : ({{ $wireItem }}.costCenter || '—')">{{ ($item['chargeTo'] === 'po' ? $item['po'] : $item['costCenter']) ?: '—' }}</span>
                                <span class="hidden truncate font-mono text-[11px] text-slate-600 lg:block" x-text="{{ $wireItem }}.invoiceNo || '—'">{{ $item['invoiceNo'] ?: '—' }}</span>
                                <span class="shrink-0 text-right font-display text-sm font-bold text-slate-900 tabular-nums" x-text="peso(parseFloat(String({{ $wireItem }}.amount ?? '').replace(/,/g, '')) || 0)">{{ $peso(CreateRfp::toAmount($item['amount'])) }}</span>
                                <x-filament::icon :icon="Heroicon::ChevronDown" class="size-4 shrink-0 text-slate-400 transition" x-bind:class="openItem === {{ $index }} ? 'rotate-180' : ''" />
                            </button>

                            {{-- Editor --}}
                            <div x-show="openItem === {{ $index }}" @style(['display: none' => $index !== 0]) class="flex flex-col gap-4 border-t border-slate-200/60 px-3 pt-4 pb-4 lg:px-4">
                                <x-my.field label="Supporting document" :error="'items.'.$index.'.upload'" hint="Official receipt, sales invoice or bill · PDF, JPG/PNG, Excel, Word or CSV, up to 10 MB">
                                    @if ($item['upload'])
                                        <span class="flex items-center justify-between gap-3 rounded-xl bg-indigo-50/70 p-2.5">
                                            <span class="flex min-w-0 items-center gap-2.5">
                                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white text-indigo-600 shadow-sm">
                                                    <x-filament::icon :icon="$fileIcon($item['upload']->getClientOriginalName())" class="size-5" />
                                                </span>
                                                <span class="min-w-0">
                                                    <span class="block truncate text-sm font-semibold text-slate-900">{{ $item['upload']->getClientOriginalName() }}</span>
                                                    <span class="block text-[11px] text-slate-500">{{ $fileSize($item['upload']) }}</span>
                                                </span>
                                            </span>
                                            <span class="flex shrink-0 items-center gap-1">
                                                <button type="button" wire:click="readReceipt({{ $index }})" class="flex items-center gap-1 rounded-lg bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white shadow-sm transition active:scale-95">
                                                    <x-filament::icon :icon="Heroicon::Sparkles" class="size-3.5" />
                                                    Read receipt
                                                </button>
                                                <button type="button" wire:click="$set('items.{{ $index }}.upload', null)" aria-label="Remove file" class="flex size-8 items-center justify-center rounded-full text-slate-400 transition hover:bg-white hover:text-rose-600">
                                                    <x-filament::icon :icon="Heroicon::XMark" class="size-4" />
                                                </button>
                                            </span>
                                        </span>
                                    @else
                                        <label class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-indigo-200 bg-white/50 px-4 py-5 text-center transition hover:bg-white/80">
                                            <input type="file" wire:model="items.{{ $index }}.upload" accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx,.csv" class="sr-only" />
                                            <x-filament::icon :icon="Heroicon::OutlinedArrowUpTray" class="size-6 text-indigo-500" />
                                            <span class="text-sm font-semibold text-indigo-600" wire:loading.remove wire:target="items.{{ $index }}.upload">Add the receipt or invoice</span>
                                            <span class="text-sm font-semibold text-indigo-600" wire:loading wire:target="items.{{ $index }}.upload">Uploading…</span>
                                        </label>
                                    @endif
                                </x-my.field>

                                {{-- Reimbursement: who the receipt is from --}}
                                <div class="grid grid-cols-1 gap-4 rounded-xl bg-violet-50/60 p-3 lg:grid-cols-2" x-show="isReimbursement" @style(['display: none' => ! $isReimbursement])>
                                    <x-my.field label="Vendor company" :error="'items.'.$index.'.vendorCompany'">
                                        <x-my.select :options="$options['companies']" wire:model="items.{{ $index }}.vendorCompany" />
                                    </x-my.field>
                                    <x-my.field label="Vendor type" :error="'items.'.$index.'.vendorType'">
                                        <div class="{{ $segmented }}">
                                            @foreach (['vendor' => 'Vendor', 'one_time' => 'One-time vendor'] as $value => $label)
                                                <label {{ $choice("items.{$index}.vendorType", $value, $segment, $segmentOn, $segmentOff) }}>
                                                    <input type="radio" wire:model="items.{{ $index }}.vendorType" value="{{ $value }}" class="sr-only" />
                                                    {{ $label }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </x-my.field>
                                    <x-my.field label="Vendor" :error="'items.'.$index.'.vendor'" class="lg:col-span-2" x-show="{{ $wireItem }}.vendorType === 'vendor'" :style="$item['vendorType'] !== 'vendor' ? 'display: none' : null">
                                        <x-my.select :options="$options['vendors']" placeholder="Choose a vendor" wire:model="items.{{ $index }}.vendor" />
                                    </x-my.field>
                                    <div class="grid grid-cols-1 gap-4 lg:col-span-2 lg:grid-cols-3" x-show="{{ $wireItem }}.vendorType === 'one_time'" @style(['display: none' => $item['vendorType'] !== 'one_time'])>
                                        <x-my.field label="Name" :error="'items.'.$index.'.oneTimeName'">
                                            <input type="text" wire:model="items.{{ $index }}.oneTimeName" class="{{ $input }}" />
                                        </x-my.field>
                                        <x-my.field label="TIN" :error="'items.'.$index.'.oneTimeTin'">
                                            <input type="text" wire:model="items.{{ $index }}.oneTimeTin" maxlength="15" placeholder="000-000-000-000" class="{{ $input }}" />
                                        </x-my.field>
                                        <x-my.field label="Address" :error="'items.'.$index.'.oneTimeAddress'">
                                            <input type="text" wire:model="items.{{ $index }}.oneTimeAddress" placeholder="Street, city" class="{{ $input }}" />
                                        </x-my.field>
                                    </div>
                                </div>

                                <x-my.field label="Item details" :error="'items.'.$index.'.description'">
                                    <input type="text" wire:model="items.{{ $index }}.description" maxlength="150" placeholder="e.g. A4 copy paper 80gsm (20 reams)" class="{{ $input }}" />
                                </x-my.field>

                                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                    <x-my.field label="Charge to" :error="'items.'.$index.'.'.($item['chargeTo'] === 'po' ? 'po' : 'costCenter')">
                                        <div class="{{ $segmented }}">
                                            @foreach (['cost_center' => 'Cost center', 'po' => 'PO number'] as $value => $label)
                                                <label {{ $choice("items.{$index}.chargeTo", $value, $segment, $segmentOn, $segmentOff) }}>
                                                    <input type="radio" wire:model="items.{{ $index }}.chargeTo" value="{{ $value }}" class="sr-only" />
                                                    {{ $label }}
                                                </label>
                                            @endforeach
                                        </div>
                                        <span x-show="{{ $wireItem }}.chargeTo === 'cost_center'" @style(['display: none' => $item['chargeTo'] !== 'cost_center'])>
                                            <x-my.select :options="$options['costCenters']" placeholder="Choose a cost center" wire:model="items.{{ $index }}.costCenter" />
                                        </span>
                                        <input type="text" wire:model="items.{{ $index }}.po" placeholder="e.g. PO-2026-0941" class="{{ $input }}" x-show="{{ $wireItem }}.chargeTo === 'po'" @style(['display: none' => $item['chargeTo'] !== 'po']) />
                                    </x-my.field>

                                    <div class="grid grid-cols-2 gap-3">
                                        <x-my.field label="Category" :error="'items.'.$index.'.category'">
                                            <x-my.select :options="array_keys($options['categories'])" wire:model.live="items.{{ $index }}.category" />
                                        </x-my.field>
                                        <x-my.field label="Sub category" :error="'items.'.$index.'.subcategory'">
                                            <x-my.select :options="$options['categories'][$item['category']] ?? []" :placeholder="filled($item['category']) ? 'Select…' : 'Category first'" wire:model.live="items.{{ $index }}.subcategory" :disabled="blank($item['category'])" />
                                        </x-my.field>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3 lg:grid-cols-[1fr_1fr_1.2fr] lg:gap-4">
                                    <x-my.field label="Invoice no." :error="'items.'.$index.'.invoiceNo'">
                                        <input type="text" wire:model="items.{{ $index }}.invoiceNo" maxlength="50" placeholder="e.g. NBS-88910" class="{{ $input }}" />
                                    </x-my.field>
                                    <x-my.field label="Invoice date" :error="'items.'.$index.'.invoiceDate'">
                                        <input type="date" wire:model="items.{{ $index }}.invoiceDate" max="{{ today()->toDateString() }}" class="{{ $input }}" />
                                    </x-my.field>
                                    <x-my.field label="Amount (PHP)" :error="'items.'.$index.'.amount'" class="col-span-2 lg:col-span-1">
                                        <span class="relative block">
                                            <span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 font-display text-base font-bold text-slate-400">₱</span>
                                            <input type="text" inputmode="decimal" wire:model="items.{{ $index }}.amount" placeholder="0.00" class="{{ $input }} pl-8 text-right font-display text-base font-bold tabular-nums" />
                                        </span>
                                    </x-my.field>
                                </div>

                                @if (count($items) > 1)
                                    <button type="button" wire:click="removeItem({{ $index }})" x-on:click="openItem = null" class="flex items-center gap-1 self-end text-xs font-semibold text-rose-600 transition hover:text-rose-700">
                                        <x-filament::icon :icon="Heroicon::OutlinedTrash" class="size-4" />
                                        Remove item
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    @error('items')
                        <span class="text-xs font-medium text-rose-600">{{ $message }}</span>
                    @enderror

                    <button type="button" x-on:click="$wire.addItem().then((index) => openItem = index)" class="flex items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-indigo-200 bg-white/40 py-3 text-sm font-semibold text-indigo-600 transition hover:bg-white/80 active:scale-[0.99] lg:hidden">
                        <x-filament::icon :icon="Heroicon::OutlinedPlusCircle" class="size-5" />
                        Add item
                    </button>
                </section>

                {{-- Phones: approval route --}}
                <section class="my-glass flex flex-col gap-3 rounded-[20px] p-4 lg:hidden">
                    <h2 class="font-display text-base font-bold text-slate-900">Approval route</h2>
                    <div class="flex flex-wrap items-center gap-x-1.5 gap-y-2">
                        <template x-for="(stop, position) in route" :key="stop">
                            <span class="flex items-center gap-1.5">
                                <x-filament::icon :icon="Heroicon::ArrowRight" class="size-3.5 text-slate-400" x-show="position > 0" />
                                <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-800" x-text="stop"></span>
                            </span>
                        </template>
                    </div>
                    <p class="text-[11px] text-slate-500">Set by the approval matrix. You'll be notified at every step.</p>
                </section>

                {{-- Phones: step 2 actions --}}
                <div class="fixed inset-x-0 bottom-0 z-40 border-t border-white/80 bg-white/85 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur-2xl lg:hidden" x-show="step === 2" style="display: none">
                    <div class="mx-auto flex max-w-xl items-center justify-between gap-3">
                        <span class="flex flex-col">
                            <span class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Total · {{ count($items) }} {{ str('item')->plural(count($items)) }}</span>
                            <span class="font-display text-xl font-extrabold text-slate-900 tabular-nums" x-text="peso(total)">{{ $peso($this->total()) }}</span>
                        </span>
                        <button type="button" x-on:click="confirm = true" class="flex items-center gap-1.5 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/25 transition active:scale-[0.98]">
                            Submit RFP
                            <x-filament::icon :icon="Heroicon::OutlinedPaperAirplane" class="size-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Laptops: sticky summary --}}
        <aside class="hidden lg:sticky lg:top-8 lg:col-span-4 lg:block">
            <section class="my-glass flex flex-col gap-5 rounded-[20px] p-6">
                <h2 class="font-display text-lg font-bold text-slate-900">Summary</h2>

                <dl class="flex flex-col gap-2.5 text-xs">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">RFP type</dt><dd class="font-semibold text-slate-900" x-text="isReimbursement ? 'Reimbursement' : 'Standard'">{{ $isReimbursement ? 'Reimbursement' : 'Standard' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Due</dt><dd class="font-semibold text-slate-900" x-text="`${$wire.form.dueType === 'urgent' ? 'Urgent' : 'Standard'} · ${dueDates[$wire.form.dueType]}`">{{ CreateRfp::DUE_TYPES[$this->form['dueType']]['label'] }} · {{ $dueDates[$this->form['dueType']] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Payee</dt><dd class="truncate font-semibold text-slate-900" x-text="payee">{{ $isReimbursement ? 'You (reimbursement)' : ($this->form['vendor'] ?: 'Not chosen yet') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Items</dt><dd class="font-semibold text-slate-900">{{ count($items) }}</dd></div>
                </dl>

                <div class="rounded-2xl bg-indigo-50/70 p-4">
                    <span class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Total</span>
                    <p class="mt-1 font-display text-3xl font-extrabold tracking-tight text-slate-900 tabular-nums" x-text="peso(total)">{{ $peso($this->total()) }}</p>
                </div>

                <div>
                    <span class="text-xs font-bold text-slate-900">Approval route</span>
                    <ol class="relative mt-3 flex flex-col gap-3 pl-5">
                        <span class="absolute top-1.5 bottom-1.5 left-[5px] w-0.5 bg-indigo-100" aria-hidden="true"></span>
                        <template x-for="(stop, position) in route" :key="stop">
                            <li class="relative flex items-center justify-between text-xs">
                                <span class="absolute -left-5 size-3 rounded-full ring-4 ring-white/80" x-bind:class="position === 0 ? 'bg-indigo-600' : 'bg-indigo-200'"></span>
                                <span x-bind:class="position === 0 ? 'font-semibold text-slate-900' : 'text-slate-600'" x-text="stop"></span>
                                <span class="text-[10px] text-slate-400" x-text="position === 0 ? 'First stop' : ''"></span>
                            </li>
                        </template>
                    </ol>
                </div>

                <div class="flex flex-col gap-2">
                    <button type="button" x-on:click="confirm = true" class="flex items-center justify-center gap-2 rounded-xl bg-indigo-600 py-3 text-sm font-semibold text-white shadow-[0_8px_20px_rgba(79,70,229,0.25)] transition hover:bg-indigo-500">
                        Submit RFP
                        <x-filament::icon :icon="Heroicon::ArrowRight" class="size-4" />
                    </button>
                    <button type="button" wire:click="saveDraft" class="rounded-xl bg-white/80 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-white">Save as draft</button>
                </div>

                <p class="flex items-start gap-2 rounded-xl bg-white/50 p-2.5 text-[11px] leading-snug text-slate-500">
                    <x-filament::icon :icon="Heroicon::OutlinedShieldCheck" class="size-4 shrink-0 text-indigo-600" />
                    AP Review checks it first. You'll be notified at every step.
                </p>
            </section>
        </aside>
    </div>

    {{-- Confirm: a bottom sheet on phones, a dialog on laptops --}}
    <div x-cloak x-show="confirm" class="fixed inset-0 z-50 flex items-end justify-center lg:items-center">
        <div x-show="confirm" x-transition.opacity x-on:click="confirm = false" class="absolute inset-0 bg-slate-900/25 backdrop-blur-[2px]"></div>
        <div
            x-show="confirm"
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="translate-y-full lg:translate-y-4 lg:opacity-0"
            x-transition:enter-end="translate-x-0 translate-y-0 lg:opacity-100"
            class="relative w-full max-w-xl rounded-t-[32px] border-t border-white bg-white/95 p-6 pt-3 shadow-2xl backdrop-blur-2xl lg:max-w-md lg:rounded-[28px] lg:pt-6"
        >
            <span class="mx-auto mb-4 block h-1 w-10 rounded-full bg-slate-300 lg:hidden"></span>
            <h2 class="font-display text-2xl font-extrabold tracking-tight text-slate-900">Submit <span x-text="peso(total)"></span>?</h2>
            <p class="mt-1 text-sm text-slate-500">It goes to AP Review first.</p>

            <dl class="mt-5 flex flex-col gap-3 rounded-2xl bg-slate-50/80 p-4 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Payee</dt><dd class="truncate font-semibold text-slate-900" x-text="payee"></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Items</dt><dd class="font-semibold text-slate-900">{{ count($items) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Due</dt><dd class="font-semibold text-slate-900" x-text="dueDates[$wire.form.dueType]"></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">First stop</dt><dd class="flex items-center gap-2 font-semibold text-slate-900"><span class="size-2 rounded-full bg-emerald-500"></span>AP Review</dd></div>
            </dl>

            <div class="mt-6 flex flex-col gap-2">
                <button type="button" x-on:click="submit()" x-bind:disabled="submitting" class="flex items-center justify-center gap-2 rounded-full bg-indigo-600 py-3.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/25 transition active:scale-[0.98] disabled:opacity-60">
                    <span x-text="submitting ? 'Submitting…' : 'Submit RFP'">Submit RFP</span>
                    <x-filament::icon :icon="Heroicon::ArrowRight" class="size-4" />
                </button>
                <button type="button" x-on:click="confirm = false" class="py-2.5 text-sm font-semibold text-slate-500 hover:text-slate-800">Cancel</button>
            </div>
        </div>
    </div>

    {{-- Submitted: full screen on phones, a dialog on laptops --}}
    @if ($submitted)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-[#f5f6fb] p-4 lg:bg-slate-900/25 lg:pl-64 lg:backdrop-blur-sm">
            <div class="my-aurora lg:hidden" aria-hidden="true">
                <span class="top-10 left-1/2 size-72 -translate-x-1/2 bg-[#A7F3D0] opacity-40"></span>
                <span class="top-1/3 -left-16 size-64 bg-[#C4B5FD] opacity-40"></span>
            </div>

            <div class="relative flex w-full max-w-md flex-col items-center rounded-[28px] p-6 text-center lg:bg-white/90 lg:p-10 lg:shadow-2xl lg:backdrop-blur-2xl">
                <div class="relative mb-6 flex size-32 items-center justify-center">
                    <span class="absolute inset-0 animate-pulse rounded-full bg-emerald-200/40"></span>
                    <span class="absolute inset-3 rounded-full bg-white/80 shadow-sm backdrop-blur-md"></span>
                    <span class="relative flex size-16 items-center justify-center rounded-full bg-emerald-600 text-white shadow-[0_12px_28px_rgba(5,150,105,0.3)]">
                        <x-filament::icon :icon="Heroicon::Check" class="size-8" />
                    </span>
                </div>

                <h2 class="font-display text-2xl font-extrabold tracking-tight text-slate-900">RFP submitted</h2>
                <p class="mt-3 inline-flex items-center gap-2 rounded-full bg-white/80 px-3.5 py-1.5 shadow-sm">
                    <span class="font-mono text-xs font-medium text-slate-600">{{ $submitted['number'] }}</span>
                    <span class="size-1 rounded-full bg-slate-300"></span>
                    <span class="font-display text-sm font-bold text-indigo-700 tabular-nums">{{ $peso($submitted['amount']) }}</span>
                </p>
                <p class="mt-4 max-w-[300px] text-sm leading-relaxed text-slate-500">AP Review will check it first. We'll notify you at every step.</p>
                <p class="mt-2 rounded-full bg-amber-50 px-3 py-1 text-[11px] font-semibold text-amber-700">Preview only. Nothing was saved yet.</p>

                <div class="mt-8 flex w-full flex-col gap-2">
                    <a href="{{ MyRequests::getUrl() }}" class="flex items-center justify-center gap-2 rounded-xl bg-indigo-600 py-3.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/25 transition hover:bg-indigo-500">
                        View my requests
                        <x-filament::icon :icon="Heroicon::ArrowRight" class="size-4" />
                    </a>
                    <a href="{{ Dashboard::getUrl() }}" class="py-2.5 text-sm font-semibold text-slate-500 hover:text-slate-800">Back to home</a>
                </div>
            </div>
        </div>
    @endif
</div>
