@php
    use App\Enums\RfpStatus;
    use App\Filament\Resources\Rfps\RfpResource;
    use App\Support\Peso;

    $revision = collect($items)->where('status', RfpStatus::ForRevision)->count();
    $drafts = collect($items)->where('status', RfpStatus::Draft)->count();
@endphp

<x-filament-widgets::widget>
    @if (count($items))
        <div class="overflow-hidden rounded-xl border-s-4 border-warning-500 bg-warning-50 p-4 ring-1 ring-warning-200 dark:bg-warning-500/10 dark:ring-warning-500/20">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-6 w-6 text-warning-600 dark:text-warning-400" />
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                    Action needed ·
                    <span class="text-warning-700 dark:text-warning-400">{{ $revision }} {{ str('request')->plural($revision) }} returned for revision</span>
                    · {{ $drafts }} {{ str('draft')->plural($drafts) }} not yet submitted
                </h2>
            </div>

            <div class="flex flex-col gap-2">
                @foreach ($items as $item)
                    @php($isRevision = $item['status'] === RfpStatus::ForRevision)
                    <div class="flex flex-col gap-3 rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-950/5 md:flex-row md:items-center dark:bg-gray-900 dark:ring-white/10">
                        <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1 md:w-96 md:shrink-0">
                            <x-filament::badge :color="$item['status']->getColor()">{{ $item['status']->getLabel() }}</x-filament::badge>
                            <span class="font-semibold text-gray-950 dark:text-white">{{ $item['rfp_number'] }}</span>
                            <span class="truncate text-sm text-gray-600 dark:text-gray-400">{{ $item['payee'] }}</span>
                            <span class="text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ Peso::format($item['amount']) }}</span>
                        </div>

                        <div @class([
                            'flex min-w-0 flex-1 items-center gap-2 rounded-md px-3 py-1.5 text-sm',
                            'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400' => $isRevision,
                            'bg-gray-50 text-gray-600 dark:bg-white/5 dark:text-gray-400' => ! $isRevision,
                        ])>
                            <x-filament::icon :icon="$isRevision ? 'heroicon-m-chat-bubble-left-ellipsis' : 'heroicon-m-pencil-square'" class="h-4 w-4 shrink-0" />
                            <span @class(['truncate', 'italic' => $isRevision])>{{ $isRevision ? '"'.$item['note'].'"' : $item['note'] }}</span>
                            <span class="ms-auto whitespace-nowrap text-xs text-gray-500">
                                {{ $isRevision ? 'Returned' : 'Saved' }} {{ $item['at']->diffForHumans() }}
                            </span>
                        </div>

                        <x-filament::button
                            tag="a"
                            :href="RfpResource::getUrl('index')"
                            size="sm"
                            :color="$isRevision ? 'warning' : 'gray'"
                            :icon="$isRevision ? 'heroicon-m-pencil-square' : 'heroicon-m-arrow-right'"
                            icon-position="after"
                            class="shrink-0"
                        >
                            {{ $isRevision ? 'Revise' : 'Continue draft' }}
                        </x-filament::button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
