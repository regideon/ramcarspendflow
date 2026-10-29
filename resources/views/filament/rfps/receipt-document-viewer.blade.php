@php
    /**
     * Read receipt review: the uploaded documents, shown beside what the AI read so the
     * requestor can compare value by value. PDFs use the browser's viewer; images can be
     * zoomed and rotated. A receipt's "Show" button (receipt-locate) switches file and page.
     *
     * Layout lives in spendflow-ui.css (.sf-receipt-*): x-show drops inline display styles,
     * which used to collapse the PDF to the browser's default 150px iframe height.
     *
     * Also used by the P2P review (ReviewAccountsPayable) for the vendor's invoice.
     *
     * @var list<array{url: string, mime: string, name: string}> $documents
     * @var string|null $emptyMessage
     */
    $documents = array_values($documents ?? []);
    $emptyMessage ??= 'The document can\'t be shown here. Open it from Supporting Document to compare.';
@endphp

<div
    x-data="{
        active: 0,
        page: null,
        zoom: 1,
        rotate: 0,
        reset() { this.zoom = 1; this.rotate = 0 },
        pdfSrc(url) { return url + '#' + (this.page ? 'page=' + this.page + '&' : '') + 'view=FitH' },
    }"
    x-on:sf-receipt-show.window="active = $event.detail.file ?? 0; page = $event.detail.page ?? null; reset()"
    class="sf-receipt-viewer"
>
    @if ($documents === [])
        <div class="sf-receipt-empty">
            {{ $emptyMessage }}
        </div>
    @else
        @foreach ($documents as $index => $document)
            @php $isPdf = $document['mime'] === 'application/pdf'; @endphp

            <div x-show="active === {{ $index }}" class="sf-receipt-doc" wire:key="receipt-doc-{{ $index }}">
                {{-- Toolbar --}}
                <div class="sf-receipt-toolbar">
                    <x-filament::icon :icon="$isPdf ? \Filament\Support\Icons\Heroicon::OutlinedDocumentText : \Filament\Support\Icons\Heroicon::OutlinedPhoto" class="sf-receipt-toolbar-icon" />
                    <span class="sf-receipt-toolbar-name" title="{{ $document['name'] }}">
                        @if (count($documents) > 1)
                            <span class="sf-receipt-toolbar-count">{{ $index + 1 }}/{{ count($documents) }}</span>
                        @endif
                        {{ $document['name'] }}
                        @if ($isPdf)
                            <span x-show="page" x-text="'· page ' + page" class="sf-receipt-toolbar-count"></span>
                        @endif
                    </span>

                    @unless ($isPdf)
                        <x-filament::icon-button icon="heroicon-m-magnifying-glass-minus" color="gray" size="sm" label="Zoom out" x-on:click="zoom = Math.max(0.25, +(zoom - 0.25).toFixed(2))" />
                        <span x-text="Math.round(zoom * 100) + '%'" class="sf-receipt-zoom"></span>
                        <x-filament::icon-button icon="heroicon-m-magnifying-glass-plus" color="gray" size="sm" label="Zoom in" x-on:click="zoom = Math.min(4, +(zoom + 0.25).toFixed(2))" />
                        <x-filament::icon-button icon="heroicon-m-arrow-path" color="gray" size="sm" label="Rotate" x-on:click="rotate = (rotate + 90) % 360" />
                        <x-filament::icon-button icon="heroicon-m-arrows-pointing-in" color="gray" size="sm" label="Fit to width" x-on:click="reset()" />
                    @endunless

                    <x-filament::link :href="$document['url']" target="_blank" icon="heroicon-m-arrow-top-right-on-square" size="sm">
                        Open in new tab
                    </x-filament::link>
                </div>

                {{-- Document: fills the rest of the panel --}}
                <div class="sf-receipt-stage">
                    @if ($isPdf)
                        {{-- Re-created when the page changes, so the viewer opens on that page --}}
                        <template x-for="src in [pdfSrc(@js($document['url']))]" :key="src">
                            <iframe x-bind:src="src" title="{{ $document['name'] }}" class="sf-receipt-frame"></iframe>
                        </template>
                    @else
                        <div class="sf-receipt-image-scroll">
                            <img
                                src="{{ $document['url'] }}"
                                alt="{{ $document['name'] }}"
                                x-bind:style="`max-width:${zoom === 1 && rotate === 0 ? '100%' : 'none'}; width:${zoom === 1 ? 'auto' : (zoom * 100) + '%'}; transform:rotate(${rotate}deg);`"
                                x-on:dblclick="zoom = zoom === 1 ? 2 : 1"
                                class="sf-receipt-image"
                            />
                        </div>
                    @endif
                </div>
            </div>
        @endforeach

        @if (count($documents) > 1)
            <div class="sf-receipt-tabs">
                @foreach ($documents as $index => $document)
                    <button type="button" x-on:click="active = {{ $index }}; page = null; reset()"
                        x-bind:class="active === {{ $index }} && 'sf-receipt-tab-active'" class="sf-receipt-tab">
                        {{ $index + 1 }}. {{ \Illuminate\Support\Str::limit($document['name'], 24) }}
                    </button>
                @endforeach
            </div>
        @endif
    @endif
</div>
