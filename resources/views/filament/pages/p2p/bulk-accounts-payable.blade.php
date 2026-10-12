@php
    use Illuminate\Support\Facades\Storage;

    /**
     * Ported from the legacy bulk-accounts-payable view:
     *   [ Attachment ]  [ Header              ]
     *    ^ sticky at xl [ Items               ]
     *                   [ Tax   ] [ Summary   ]
     *                   [ Approval History    ]
     *   [ Actions — full width, sticky footer  ]
     */
    $attachmentPaths = collect($this->data['attachments'] ?? [])
        ->map(fn ($attachment) => is_array($attachment) ? ($attachment['path'] ?? null) : $attachment)
        ->filter(fn ($path) => is_string($path) && $path !== '')
        ->values()
        ->all();

    $hasInvoice = $attachmentPaths !== [];
    $invoiceUrl = $hasInvoice ? Storage::disk('public')->url($attachmentPaths[0]) : null;
@endphp

<x-filament-panels::page>
    <form wire:submit="submit" x-data="{ isStuck: true }">
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            {{-- Left: the attached invoice, viewed against the data entry.
                 Hidden below xl so the form/table gets the full width. --}}
            <div class="hidden xl:col-span-1 xl:block">
                <x-filament::section
                    heading="Attached Invoice"
                    icon="heroicon-m-paper-clip"
                    icon-color="primary"
                    class="xl:sticky xl:top-6"
                >
                    @if ($hasInvoice)
                        <div x-data="{ active: 0 }" class="space-y-3">
                            @if (count($attachmentPaths) > 1)
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($attachmentPaths as $index => $path)
                                        <button
                                            type="button"
                                            wire:key="attachment-tab-{{ $index }}"
                                            x-on:click="active = {{ $index }}"
                                            x-bind:class="active === {{ $index }}
                                                ? 'border-primary-600 bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300'
                                                : 'border-gray-300 bg-white text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300'"
                                            class="rounded-md border px-2.5 py-1 text-xs font-medium"
                                        >
                                            File {{ $index + 1 }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            @foreach ($attachmentPaths as $index => $path)
                                <iframe
                                    wire:key="attachment-frame-{{ $index }}"
                                    @if (count($attachmentPaths) > 1)
                                        x-show="active === {{ $index }}"
                                    @endif
                                    src="{{ Storage::disk('public')->url($path) }}"
                                    title="Attached invoice {{ $index + 1 }}"
                                    class="h-[70vh] w-full rounded-lg border border-gray-200 bg-white dark:border-white/10"
                                ></iframe>
                            @endforeach

                            <a
                                href="{{ $invoiceUrl }}"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                            >
                                Open in new tab
                            </a>
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            No invoice attachment uploaded.
                        </p>
                    @endif
                </x-filament::section>
            </div>

            {{-- Right: header, line items, tax/summary and history. --}}
            <div class="space-y-6 xl:col-span-2">
                @if ($invoiceUrl)
                    {{-- Below xl the invoice panel is hidden, so surface a view action. --}}
                    <div class="xl:hidden">
                        <x-filament::button
                            tag="a"
                            href="{{ $invoiceUrl }}"
                            target="_blank"
                            rel="noopener"
                            icon="heroicon-m-paper-clip"
                            color="primary"
                            size="sm"
                        >
                            View invoice
                        </x-filament::button>
                    </div>
                @endif

                {{ $this->form }}
            </div>
        </div>

        {{-- Full-width footer actions: normal flow below xl, sticky at xl. --}}
        <div
            class="mt-6 pt-4 xl:sticky xl:bottom-0 xl:z-10 xl:-mx-8 xl:px-8 xl:pb-4 transition-all duration-300"
            x-bind:class="isStuck ? 'border rounded border-gray-200 dark:border-white/10 xl:bg-white/95 xl:backdrop-blur dark:xl:bg-gray-900/95' : 'border-transparent bg-transparent'"
        >
            <x-filament::actions :actions="$this->getFooterActions()" />
        </div>
        <div x-intersect="isStuck = false" x-intersect:leave="isStuck = true" class="h-1"></div>
    </form>

    <x-filament-actions::modals />
</x-filament-panels::page>
