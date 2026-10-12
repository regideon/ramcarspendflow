@php
    use App\Filament\Resources\P2p\Approvals\ApprovalResource;
    use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
    use App\Support\Peso;
@endphp

<x-filament-widgets::widget>
    @if (count($items))
        <div class="overflow-hidden rounded-xl border-s-4 border-warning-500 bg-warning-50 p-4 ring-1 ring-warning-200 dark:bg-warning-500/10 dark:ring-warning-500/20">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <x-filament::icon icon="heroicon-o-arrow-uturn-left" class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Back on your desk</h2>
                <span class="text-sm text-gray-600 dark:text-gray-400">· {{ count($items) }} {{ str('item')->plural(count($items)) }} returned or unblocked</span>
            </div>

            <div class="flex flex-col gap-2">
                @foreach ($items as $item)
                    @php($rework = $item['action'] === 'Rework')
                    <div class="flex flex-col gap-3 rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-950/5 md:flex-row md:items-center dark:bg-gray-900 dark:ring-white/10">
                        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                            @include('filament.widgets.accounting.ap-processor._module', ['module' => $item['module']])
                            <span class="font-semibold text-gray-950 dark:text-white">{{ $item['reference'] }}</span>
                            <span class="text-gray-500 dark:text-gray-400">{{ $item['by'] }}:</span>
                            <span @class(['font-medium', 'italic text-danger-700 dark:text-danger-400' => $rework, 'text-gray-700 dark:text-gray-300' => ! $rework])>
                                {{ $rework ? '"'.$item['remark'].'"' : $item['remark'] }}
                            </span>
                            <span class="text-gray-400">·</span>
                            <span class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ Peso::format($item['amount']) }}</span>
                            <span class="text-gray-400">·</span>
                            <span class="text-xs text-gray-500">{{ $item['at']->diffForHumans() }}</span>
                        </div>
                        <x-filament::button
                            tag="a"
                            :href="$item['module'] === 'RFP' ? RfpApprovalResource::getUrl('index') : ApprovalResource::getUrl('index')"
                            size="sm"
                            :color="$rework ? 'warning' : 'info'"
                            :icon="$rework ? 'heroicon-m-pencil-square' : 'heroicon-m-play'"
                            class="shrink-0"
                        >
                            {{ $item['action'] }}
                        </x-filament::button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
