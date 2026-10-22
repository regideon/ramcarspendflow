<x-filament-panels::page>
    <div
        x-data="{
            open: @js($selectedRecordId !== null),
            width: Number(localStorage.getItem('p2p.split.width') ?? 42),
            dragging: false,
            start(event) {
                this.dragging = true;
                event.preventDefault();
                document.body.style.userSelect = 'none';
            },
            move(event) {
                if (! this.dragging) return;
                const rect = this.$refs.container.getBoundingClientRect();
                const percent = ((rect.right - event.clientX) / rect.width) * 100;
                this.width = Math.min(70, Math.max(30, percent));
            },
            stop() {
                if (! this.dragging) return;
                this.dragging = false;
                document.body.style.userSelect = '';
                localStorage.setItem('p2p.split.width', this.width);
            },
            preset(value) {
                this.width = value;
                localStorage.setItem('p2p.split.width', value);
            },
            reset() {
                this.preset(42);
            },
            setUrl(id) {
                const url = new URL(window.location.href);
                url.searchParams.set('record', id);
                window.history.replaceState({}, '', url);
            },
            clearUrl() {
                const url = new URL(window.location.href);
                url.searchParams.delete('record');
                window.history.replaceState({}, '', url);
            },
        }"
        x-on:p2p-record-selected.window="open = true; setUrl($event.detail.id)"
        x-on:p2p-record-closed.window="open = false; clearUrl()"
        x-on:pointermove.window="move($event)"
        x-on:pointerup.window="stop()"
        x-ref="container"
        class="flex flex-col gap-0 lg:flex-row lg:items-start"
    >
        <div class="min-w-0 flex-1" :class="open ? 'hidden lg:block' : ''">
            {{ $this->content }}
        </div>

        <div
            x-show="open"
            x-cloak
            class="hidden w-2 shrink-0 cursor-col-resize self-stretch rounded-full bg-gray-200 transition hover:bg-primary-400 lg:block dark:bg-white/10"
            x-on:pointerdown="start($event)"
            x-on:dblclick="reset()"
            title="Drag to resize"
        ></div>

        <div
            x-show="open"
            x-cloak
            class="p2p-split-panel w-full lg:sticky lg:top-6 lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto"
            :style="`--p2p-panel-width: ${width}%`"
        >
            <livewire:p2p.invoice-record-pane :invoice-id="$selectedRecordId" />
        </div>
    </div>
</x-filament-panels::page>

@once
    <style>
        @media (min-width: 1024px) {
            .p2p-split-panel {
                width: var(--p2p-panel-width);
            }
        }
    </style>
@endonce
