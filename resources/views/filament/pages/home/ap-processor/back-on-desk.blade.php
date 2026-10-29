@php
    use App\Filament\Resources\P2p\Approvals\ApprovalResource;
    use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
    use App\Support\Peso;
@endphp

@if ($backOnDesk)
    <section data-tour="back-on-desk" class="rounded-2xl border border-primary-200/80 bg-gradient-to-r from-primary-50/90 via-primary-50/50 to-white p-5 shadow-sm dark:border-primary-500/20 dark:from-primary-500/10 dark:via-primary-500/5 dark:to-gray-900">
        <div class="mb-3.5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <div class="flex size-7 items-center justify-center rounded-lg bg-primary-600 text-white">
                    <x-filament::icon icon="heroicon-m-arrow-uturn-left" class="size-4" />
                </div>
                <div>
                    <h2 class="font-display text-sm font-bold text-gray-950 dark:text-white">Back on your desk</h2>
                    <p class="text-[0.6875rem] text-gray-500 dark:text-gray-400">Returned to you with remarks, or back from a resolved escalation</p>
                </div>
            </div>
            <span class="rounded-full bg-primary-100/70 px-2.5 py-1 text-xs font-semibold text-primary-700 dark:bg-primary-500/20 dark:text-primary-300">{{ count($backOnDesk) }} to act on</span>
        </div>

        <div class="grid gap-3.5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($backOnDesk as $item)
                <div class="flex flex-col justify-between rounded-xl border border-primary-100 bg-white p-3.5 transition hover:shadow-md dark:border-white/10 dark:bg-gray-900">
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex min-w-0 items-center gap-1.5">
                                <x-sf.module-chip :module="$item['module']" />
                                <span class="truncate rounded bg-primary-50 px-2 py-0.5 font-mono text-[0.6875rem] font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">{{ $item['reference'] }}</span>
                            </div>
                            <span class="shrink-0 text-xs font-bold tabular-nums text-gray-950 dark:text-white">{{ Peso::format($item['amount']) }}</span>
                        </div>
                        <div class="mt-2.5 flex items-start gap-2 rounded-lg border border-gray-100 bg-gray-50 p-2 text-[0.6875rem] dark:border-white/5 dark:bg-white/5">
                            <x-filament::icon icon="heroicon-m-chat-bubble-left-ellipsis" class="mt-px size-4 shrink-0 text-primary-500" />
                            <div>
                                <span class="font-medium text-gray-900 dark:text-white">{{ str($item['by'])->ucfirst() }}:</span>
                                <span class="italic text-gray-500 dark:text-gray-400">“{{ $item['remark'] }}”</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-2.5 text-[0.6875rem] dark:border-white/5">
                        <span class="text-gray-400">{{ $item['at']->diffForHumans() }}</span>
                        <a
                            href="{{ $item['module'] === 'RFP' ? RfpApprovalResource::getUrl('index') : ApprovalResource::getUrl('index') }}"
                            class="flex items-center gap-0.5 font-semibold text-primary-600 hover:text-primary-800 dark:text-primary-400"
                        >
                            {{ $item['action'] }}
                            <x-filament::icon icon="heroicon-m-chevron-right" class="size-3.5" />
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif
