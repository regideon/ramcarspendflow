@props(['steps'])

{{--
    User Guide: a "Guide" pill beside the Messages bubble that starts a spotlight walkthrough
    over the page's [data-tour] sections (same behaviour as treasury-market-updates' Guide).
    Usage: <x-user-guide :steps="[['target' => 'work-queue', 'title' => '…', 'body' => '…'], …]" />
--}}
<div x-data="userGuide(@js($steps))">
    <template x-teleport="body">
        <div>
            {{-- Launcher: 12px left of the 56px Messages bubble (fixed at bottom-6 end-6), centred on it --}}
            <button
                type="button"
                x-show="! active"
                x-on:click="start()"
                class="fixed bottom-[1.875rem] end-[5.75rem] z-40 inline-flex items-center gap-2 rounded-full bg-primary-600 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-primary-600/30 transition hover:scale-105 hover:bg-primary-700 active:scale-95 focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-300"
            >
                <x-filament::icon icon="heroicon-s-light-bulb" class="size-4 text-amber-300" />
                Guide
            </button>

            <div
                x-show="active"
                x-cloak
                class="fixed inset-0 z-[80]"
                x-on:keydown.escape.window="stop()"
                x-on:keydown.arrow-right.window="next()"
                x-on:keydown.arrow-left.window="back()"
                x-on:resize.window="reposition()"
                x-on:scroll.window="reposition()"
            >
                <div x-show="! rect" class="pointer-events-none fixed inset-0 bg-white/70 dark:bg-gray-950/70"></div>
                <div class="pointer-events-none fixed rounded-2xl transition-all duration-200" x-bind:style="highlightStyle"></div>

                <div
                    x-ref="pop"
                    role="dialog"
                    aria-live="polite"
                    class="fixed max-h-[70vh] w-[min(22rem,calc(100vw-2rem))] overflow-y-auto rounded-2xl bg-white p-6 shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                    x-bind:style="popStyle"
                >
                    <div class="text-[0.6875rem] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400" x-text="'Guide · ' + (index + 1) + ' of ' + steps.length"></div>
                    <h3 class="mt-1 font-display text-base font-bold text-gray-950 dark:text-white" x-text="step?.title"></h3>
                    <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-300" x-text="step?.body"></p>

                    <div class="mt-6 flex items-center justify-between gap-3">
                        <button type="button" x-on:click="stop()" class="text-sm text-gray-500 underline underline-offset-2 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">End tour</button>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                x-on:click="back()"
                                x-bind:disabled="index === 0"
                                class="rounded-[10px] bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-white/10 dark:text-gray-200"
                            >Back</button>
                            <button
                                type="button"
                                x-on:click="next()"
                                class="rounded-[10px] bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-primary-600/30 hover:bg-primary-700"
                                x-text="index >= steps.length - 1 ? 'Finish' : 'Next'"
                            ></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

@once
    <script>
        window.userGuide ??= (steps) => ({
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
                if (! this.rect) {
                    return 'display: none;';
                }
                const dim = document.documentElement.classList.contains('dark') ? 'rgb(3 7 18 / 0.7)' : 'rgb(255 255 255 / 0.7)';

                return `top: ${this.rect.top}px; left: ${this.rect.left}px; width: ${this.rect.width}px; height: ${this.rect.height}px; box-shadow: 0 0 0 9999px ${dim}; outline: 2px solid var(--primary-600); outline-offset: 2px;`;
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
                if (! this.active) {
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
                if (! this.active || this.index === 0) {
                    return;
                }
                this.index--;
                this.focusStep(-1);
            },
            target(step) {
                return step ? document.querySelector(`[data-tour="${step.target}"]`) : null;
            },
            // Skip stops whose section is not on the page, in the direction of travel.
            focusStep(direction) {
                while (this.index >= 0 && this.index < this.steps.length && ! this.target(this.steps[this.index])) {
                    this.index += direction;
                }
                if (this.index < 0 || this.index >= this.steps.length) {
                    this.stop();
                    return;
                }
                this.el = this.target(this.step);
                this.el.scrollIntoView({ block: 'center', behavior: 'smooth' });
                setTimeout(() => this.reposition(), 350);
            },
            reposition() {
                if (! this.active || ! this.el) {
                    return;
                }
                const r = this.el.getBoundingClientRect();
                this.rect = { top: r.top - 8, left: r.left - 8, width: r.width + 16, height: r.height + 16 };

                const pop = this.$refs.pop;
                const popHeight = Math.min(pop ? pop.offsetHeight : 260, window.innerHeight * 0.7);
                const popWidth = Math.min(352, window.innerWidth - 32);
                let top = r.bottom + 20;
                if (top + popHeight > window.innerHeight - 16) {
                    top = Math.max(16, r.top - popHeight - 20);
                }
                const left = Math.min(Math.max(16, r.left), window.innerWidth - popWidth - 16);
                this.popStyle = `top: ${top}px; left: ${left}px;`;
            },
        });
    </script>
@endonce
