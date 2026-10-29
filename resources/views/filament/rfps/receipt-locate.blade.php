@php
    /**
     * Read receipt review, per receipt: where it is (file and page), with a button that shows
     * it in the document viewer on the left (receipt-document-viewer).
     *
     * @var int|null $file 0-based, among the files read
     * @var int|null $page
     * @var string|null $name
     */
@endphp

<div class="sf-receipt-locate">
    <x-filament::icon icon="heroicon-m-document-magnifying-glass" class="sf-receipt-locate-icon" />
    <span class="sf-receipt-locate-text">
        {{ collect([$name ?? 'File '.((int) $file + 1), filled($page) ? "page {$page}" : null])->filter()->implode(' · ') }}
    </span>
    <x-filament::link
        tag="button"
        size="sm"
        icon="heroicon-m-eye"
        x-on:click="$dispatch('sf-receipt-show', { file: {{ (int) $file }}, page: {{ filled($page) ? (int) $page : 'null' }} })"
    >
        Show on the left
    </x-filament::link>
</div>
