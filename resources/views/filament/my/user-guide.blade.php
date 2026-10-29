{{--
    User Guide: a step-by-step walkthrough that spotlights one part of the screen at a time.
    Started by the `start-user-guide` event. Each step's `target` matches a data-guide attribute;
    when the same key is on several elements (desktop sidebar and mobile nav) the visible one is used.
--}}
@php
    use Filament\Support\Icons\Heroicon;
@endphp

<div x-data="myUserGuide(@js($steps))" x-cloak x-on:start-user-guide.window="start()">
    <template x-teleport="body">
        <div
            x-show="active"
            class="fixed inset-0 z-90"
            x-on:keydown.escape.window="stop()"
            x-on:keydown.arrow-right.window="next()"
            x-on:keydown.arrow-left.window="back()"
            x-on:resize.window="reposition()"
            x-on:scroll.window="reposition()"
        >
            <div x-on:click="stop()" class="fixed inset-0"></div>
            <div x-show="!rect" class="pointer-events-none fixed inset-0 bg-slate-900/40"></div>
            <div class="pointer-events-none fixed rounded-2xl transition-all duration-200" :style="highlightStyle"></div>

            <div
                x-ref="pop"
                role="dialog"
                aria-modal="true"
                aria-labelledby="user-guide-title"
                class="fixed w-[min(22rem,calc(100vw-2rem))] max-h-[70vh] overflow-y-auto rounded-2xl border border-white bg-white/95 p-5 shadow-2xl backdrop-blur-xl"
                :style="popStyle"
            >
                <div class="flex items-center justify-between gap-3">
                    <span class="text-[11px] font-bold tracking-wider text-indigo-600 uppercase" x-text="'User Guide · ' + (index + 1) + ' of ' + steps.length"></span>
                    <button type="button" x-on:click="stop()" aria-label="Close guide" class="flex size-7 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                        <x-filament::icon :icon="Heroicon::XMark" class="size-4" />
                    </button>
                </div>

                <div class="mt-2 flex gap-1">
                    <template x-for="(s, i) in steps" :key="i">
                        <span class="h-1 flex-1 rounded-full transition" :class="i <= index ? 'bg-indigo-600' : 'bg-slate-200'"></span>
                    </template>
                </div>

                <h3 id="user-guide-title" class="mt-3 font-display text-base font-bold tracking-tight text-slate-900" x-text="step?.title"></h3>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-600" x-text="step?.body"></p>

                <div class="mt-5 flex items-center justify-between gap-3">
                    <button type="button" x-on:click="stop()" class="text-xs font-semibold text-slate-500 hover:text-slate-900">Skip</button>
                    <div class="flex items-center gap-2">
                        <button type="button" x-on:click="back()" x-bind:disabled="index === 0" x-bind:class="index === 0 ? 'opacity-40 cursor-not-allowed' : ''" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Back</button>
                        <button type="button" x-on:click="next()" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-slate-800" x-text="index >= steps.length - 1 ? 'Finish' : 'Next'"></button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
    window.myUserGuide ??= (steps) => ({
        steps,
        active: false,
        index: 0,
        el: null,
        rect: null,
        popStyle: '',
        get step() {
            return this.steps[this.index] ?? null;
        },
        get highlightStyle() {
            if (!this.rect) {
                return 'display: none;';
            }
            return `top: ${this.rect.top}px; left: ${this.rect.left}px; width: ${this.rect.width}px; height: ${this.rect.height}px; box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.45); outline: 2px solid #6366f1; outline-offset: 2px;`;
        },
        start() {
            this.index = 0;
            this.active = true;
            this.$nextTick(() => this.focusStep(1));
        },
        stop() {
            this.active = false;
            this.el = null;
            this.rect = null;
        },
        next() {
            if (!this.active) {
                return;
            }
            if (this.index >= this.steps.length - 1) {
                this.stop();
                return;
            }
            this.index++;
            this.focusStep(1);
        },
        back() {
            if (!this.active || this.index === 0) {
                return;
            }
            this.index--;
            this.focusStep(-1);
        },
        // The first visible element for a step: hidden copies (e.g. the desktop sidebar on a phone) are skipped.
        target(step) {
            return step
                ? [...document.querySelectorAll(`[data-guide="${step.target}"]`)].find((el) => el.getClientRects().length > 0) ?? null
                : null;
        },
        focusStep(direction) {
            while (this.index >= 0 && this.index < this.steps.length && !this.target(this.steps[this.index])) {
                this.index += direction;
            }
            if (this.index < 0 || this.index >= this.steps.length) {
                this.stop();
                return;
            }
            this.el = this.target(this.step);
            this.el.scrollIntoView({ block: 'center', behavior: 'smooth' });
            this.reposition();
            setTimeout(() => this.reposition(), 350);
        },
        reposition() {
            if (!this.active || !this.el) {
                return;
            }
            const r = this.el.getBoundingClientRect();
            this.rect = { top: r.top - 6, left: r.left - 6, width: r.width + 12, height: r.height + 12 };

            const pop = this.$refs.pop;
            const popHeight = Math.min(pop ? pop.offsetHeight : 240, window.innerHeight * 0.7);
            const popWidth = Math.min(352, window.innerWidth - 32);
            let top = r.bottom + 18;
            if (top + popHeight > window.innerHeight - 16) {
                top = Math.max(16, r.top - popHeight - 18);
            }
            const left = Math.min(Math.max(16, r.left), window.innerWidth - popWidth - 16);
            this.popStyle = `top: ${top}px; left: ${left}px;`;
        },
    });
</script>
