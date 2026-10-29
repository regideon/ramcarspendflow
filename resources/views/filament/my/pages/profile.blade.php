@php
    use App\Filament\My\Pages\Dashboard;
    use App\Filament\My\Pages\Profile;
    use App\Filament\Pages\KnowledgeBase;
    use Filament\Support\Icons\Heroicon;

    $user = $this->user();
    $roles = $this->roles();
    $device = $this->thisDevice();

    $passwordFields = [
        'current' => ['label' => 'Current password', 'autocomplete' => 'current-password'],
        'password' => ['label' => 'New password', 'autocomplete' => 'new-password'],
        'password_confirmation' => ['label' => 'Confirm new password', 'autocomplete' => 'new-password'],
    ];
@endphp

<div
    x-data="{
        sheet: false,
        reveal: {},
        alerts: @js(collect(Profile::NOTIFICATIONS)->mapWithKeys(fn (array $item): array => [$item['key'] => true])),
        channel: 'both',
        get strength() {
            const value = this.$wire.passwordForm.password ?? ''

            if (! value) return 0

            return [value.length >= 8, /[a-z]/.test(value) && /[A-Z]/.test(value), /\d/.test(value), /[^A-Za-z0-9]/.test(value)].filter(Boolean).length
        },
        close() {
            this.sheet = false
            this.reveal = {}
        },
    }"
    @keydown.escape.window="close()"
    class="flex flex-col gap-4 lg:gap-6"
>
    {{-- Mobile header --}}
    <header class="flex items-center justify-between lg:hidden">
        <a href="{{ Dashboard::getUrl() }}" aria-label="Back to home" class="my-glass flex size-10 items-center justify-center rounded-full text-slate-700 transition active:scale-95">
            <x-filament::icon :icon="Heroicon::ChevronLeft" class="size-5" />
        </a>
        <h1 class="font-display text-lg font-bold tracking-tight text-slate-900">Profile</h1>
        <span class="size-10"></span>
    </header>

    {{-- Desktop header --}}
    <header class="hidden lg:block">
        <h1 class="font-display text-3xl font-bold tracking-tight text-slate-900">Profile</h1>
        <p class="mt-1 text-sm text-slate-500">Your details, roles and settings.</p>
    </header>

    <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-12 lg:gap-6">
        {{-- Left: who you are --}}
        <div class="flex flex-col gap-4 lg:col-span-5 xl:col-span-4">
            <section class="my-glass relative flex flex-col items-center overflow-hidden rounded-3xl p-6 text-center">
                <div class="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-indigo-100/60 to-transparent"></div>

                <span class="relative flex size-20 items-center justify-center rounded-full bg-gradient-to-tr from-indigo-700 to-indigo-500 font-display text-2xl font-bold text-white shadow-[0_8px_20px_rgba(79,70,229,0.28)] lg:size-24 lg:text-3xl">
                    {{ $this->initials() }}
                </span>

                <h2 class="relative mt-3 font-display text-xl font-bold tracking-tight text-slate-900">{{ $user->name }}</h2>
                <p class="relative mt-0.5 text-sm text-slate-500">{{ $user->email }}</p>

                @if ($user->company)
                    <span class="relative mt-3 inline-flex items-center gap-1.5 rounded-full bg-white/80 px-3 py-1 text-xs font-medium text-slate-600 ring-1 ring-slate-200/70">
                        <x-filament::icon :icon="Heroicon::OutlinedBuildingOffice2" class="size-3.5" />
                        {{ $user->company->name }}
                    </span>
                @endif
            </section>

            <section class="my-glass rounded-3xl p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">My roles</h3>
                    <x-filament::icon :icon="Heroicon::OutlinedIdentification" class="size-4 text-slate-400" />
                </div>

                @forelse ($roles as $row)
                    <div @class(['py-3', 'border-t border-slate-200/60' => ! $loop->first])>
                        <p class="flex items-baseline gap-2 text-sm font-medium text-slate-900">
                            {{ $row['company'] }}
                            @if ($row['home'])
                                <span class="text-[11px] font-normal text-slate-500">Home company</span>
                            @endif
                        </p>

                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($row['roles'] as $role)
                                <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 ring-1 ring-indigo-100">{{ $role }}</span>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="py-2 text-sm text-slate-500">No roles yet. You can still create RFPs.</p>
                @endforelse

                <p class="mt-3 text-[11px] text-slate-400 italic">Roles are set by your administrator.</p>
            </section>

            <form method="POST" action="{{ filament()->getLogoutUrl() }}" class="hidden lg:block">
                @csrf
                <button type="submit" class="my-glass flex w-full items-center justify-center gap-2 rounded-2xl py-3 text-sm font-semibold text-rose-600 transition hover:bg-rose-50/70">
                    <x-filament::icon :icon="Heroicon::OutlinedArrowRightOnRectangle" class="size-5" />
                    Sign out
                </button>
            </form>
            <p class="hidden text-center text-[11px] text-slate-400 lg:block">SpendFlow v1.0</p>
        </div>

        {{-- Right: settings --}}
        <div class="flex flex-col gap-4 lg:col-span-7 xl:col-span-8">
            <section class="my-glass rounded-3xl p-5 lg:p-6">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-display text-base font-bold text-slate-900">Notifications</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Choose what we tell you about.</p>
                    </div>
                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-100">Preview</span>
                </div>

                <div class="flex flex-col">
                    @foreach (Profile::NOTIFICATIONS as $item)
                        <label @class(['flex cursor-pointer items-center justify-between gap-4 py-3', 'border-t border-slate-200/60' => ! $loop->first])>
                            <span class="min-w-0">
                                <span class="block text-sm text-slate-900">{{ $item['label'] }}</span>
                                <span class="block text-[11px] text-slate-500">{{ $item['hint'] }}</span>
                            </span>

                            <input type="checkbox" x-model="alerts.{{ $item['key'] }}" class="peer sr-only" />
                            <span class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition peer-checked:bg-indigo-600 peer-focus-visible:ring-3 peer-focus-visible:ring-indigo-500/30 after:absolute after:top-0.5 after:left-0.5 after:size-5 after:rounded-full after:bg-white after:shadow-sm after:transition peer-checked:after:translate-x-5"></span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-3 flex flex-col gap-2 border-t border-slate-200/60 pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <span class="text-sm font-medium text-slate-900">Send them by</span>
                    <div class="grid grid-cols-3 gap-1 rounded-xl bg-slate-100/80 p-1 sm:w-64">
                        @foreach (['email' => 'Email', 'in_app' => 'In-app', 'both' => 'Both'] as $value => $label)
                            <button
                                type="button"
                                x-on:click="channel = @js($value)"
                                x-bind:class="channel === @js($value) ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                                class="rounded-lg py-1.5 text-xs font-semibold transition"
                            >{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                <p class="mt-4 text-[11px] text-slate-400">These choices aren’t saved yet.</p>
            </section>

            <section class="my-glass rounded-3xl p-5 lg:p-6">
                <h3 class="mb-3 font-display text-base font-bold text-slate-900">Security</h3>

                <button type="button" x-on:click="sheet = true" class="flex w-full items-center justify-between gap-3 rounded-2xl bg-white/60 p-3 text-start transition hover:bg-white">
                    <span class="flex items-center gap-3">
                        <span class="flex size-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                            <x-filament::icon :icon="Heroicon::OutlinedKey" class="size-5" />
                        </span>
                        <span class="text-sm font-medium text-slate-900">Change password</span>
                    </span>
                    <x-filament::icon :icon="Heroicon::ChevronRight" class="size-4 text-slate-400" />
                </button>

                @if ($device)
                    <p class="mt-3 flex items-center gap-1.5 text-[11px] text-slate-500">
                        <x-filament::icon :icon="Heroicon::OutlinedComputerDesktop" class="size-4 text-slate-400" />
                        Signed in on this device: {{ $device }}
                    </p>
                @endif
            </section>

            <section class="my-glass rounded-3xl p-5 lg:p-6">
                <h3 class="mb-3 font-display text-base font-bold text-slate-900">Help</h3>

                <a href="{{ KnowledgeBase::getUrl(panel: 'hub') }}" class="flex items-center justify-between gap-3 rounded-2xl bg-white/60 p-3 transition hover:bg-white">
                    <span class="flex items-center gap-3">
                        <span class="flex size-9 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                            <x-filament::icon :icon="Heroicon::OutlinedBookOpen" class="size-5" />
                        </span>
                        <span>
                            <span class="block text-sm font-medium text-slate-900">Help center</span>
                            <span class="block text-[11px] text-slate-500">Guides for requests, approvals and payments</span>
                        </span>
                    </span>
                    <x-filament::icon :icon="Heroicon::ChevronRight" class="size-4 text-slate-400" />
                </a>
            </section>

            {{-- Phones: sign out at the end of the page --}}
            <form method="POST" action="{{ filament()->getLogoutUrl() }}" class="lg:hidden">
                @csrf
                <button type="submit" class="my-glass flex w-full items-center justify-center gap-2 rounded-2xl py-3 text-sm font-semibold text-rose-600 transition active:scale-[0.99]">
                    <x-filament::icon :icon="Heroicon::OutlinedArrowRightOnRectangle" class="size-5" />
                    Sign out
                </button>
            </form>
            <p class="text-center text-[11px] text-slate-400 lg:hidden">SpendFlow v1.0</p>
        </div>
    </div>

    {{-- Change password: a bottom sheet on phones, a dialog on laptops --}}
    <div x-cloak x-show="sheet" class="fixed inset-0 z-50 flex items-end justify-center lg:items-center lg:pl-64">
        <div x-show="sheet" x-transition.opacity x-on:click="close()" class="absolute inset-0 bg-slate-900/25 backdrop-blur-[2px]"></div>

        <form
            x-show="sheet"
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="translate-y-full lg:translate-y-4 lg:opacity-0"
            x-transition:enter-end="translate-y-0 lg:opacity-100"
            x-on:submit.prevent="$wire.updatePassword().then((done) => done && close())"
            role="dialog"
            aria-modal="true"
            aria-labelledby="change-password-title"
            class="relative w-full max-w-xl rounded-t-[32px] border-t border-white bg-white/95 p-6 pt-3 pb-[max(1.5rem,env(safe-area-inset-bottom))] shadow-2xl backdrop-blur-2xl lg:max-w-md lg:rounded-[28px] lg:pt-6"
        >
            <span class="mx-auto mb-3 block h-1.5 w-12 rounded-full bg-slate-300 lg:hidden"></span>

            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 id="change-password-title" class="font-display text-lg font-bold text-slate-900">Change password</h2>
                    <p class="mt-1 text-xs text-slate-500">You’ll stay signed in on this device.</p>
                </div>
                <button type="button" x-on:click="close()" aria-label="Close" class="flex size-8 items-center justify-center rounded-full bg-slate-100 text-slate-500 transition hover:bg-slate-200 hover:text-slate-800">
                    <x-filament::icon :icon="Heroicon::XMark" class="size-4" />
                </button>
            </div>

            <div class="mt-5 flex flex-col gap-4">
                @foreach ($passwordFields as $key => $field)
                    <x-my.field :label="$field['label']" :error="'passwordForm.'.$key">
                        <div class="relative">
                            <input
                                wire:model="passwordForm.{{ $key }}"
                                x-bind:type="reveal.{{ $key }} ? 'text' : 'password'"
                                type="password"
                                autocomplete="{{ $field['autocomplete'] }}"
                                class="w-full rounded-xl border border-slate-200/70 bg-white/70 py-2.5 ps-3.5 pe-10 text-sm text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-3 focus:ring-indigo-500/15 focus:outline-none"
                            />
                            <button type="button" x-on:click="reveal.{{ $key }} = ! reveal.{{ $key }}" x-bind:aria-label="reveal.{{ $key }} ? 'Hide password' : 'Show password'" class="absolute inset-y-0 end-0 flex w-10 items-center justify-center text-slate-400 hover:text-slate-600">
                                <x-filament::icon x-show="! reveal.{{ $key }}" :icon="Heroicon::OutlinedEye" class="size-4.5" />
                                <x-filament::icon x-cloak x-show="reveal.{{ $key }}" :icon="Heroicon::OutlinedEyeSlash" class="size-4.5" />
                            </button>
                        </div>

                        @if ($key === 'password')
                            <div class="mt-1 grid h-1.5 grid-cols-4 gap-1.5">
                                @foreach (range(1, 4) as $bar)
                                    <span class="rounded-full transition" x-bind:class="strength >= {{ $bar }} ? (strength >= 3 ? 'bg-emerald-500' : 'bg-amber-400') : 'bg-slate-200'"></span>
                                @endforeach
                            </div>
                            <p class="text-[11px] text-slate-500">
                                <span x-show="strength >= 3" class="font-semibold text-emerald-700">Strong.</span>
                                <span x-show="strength > 0 && strength < 3" class="font-semibold text-amber-700">Could be stronger.</span>
                                At least 8 characters. Mixing upper and lower case, numbers and symbols makes it stronger.
                            </p>
                        @endif
                    </x-my.field>
                @endforeach
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" x-on:click="close()" class="rounded-xl px-4 py-3 text-sm font-semibold text-slate-500 hover:text-slate-800">Cancel</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="updatePassword" class="flex-1 rounded-2xl bg-indigo-600 px-5 py-3 font-display text-sm font-bold text-white shadow-lg shadow-indigo-600/25 transition hover:bg-indigo-500 active:scale-95 disabled:opacity-60 lg:flex-none">
                    Update password
                </button>
            </div>
        </form>
    </div>
</div>
