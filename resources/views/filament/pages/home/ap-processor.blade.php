{{-- AP Processor dashboard, redesigned from Google Stitch (docs/stitch/hub-dashboard-ap/). --}}
<x-filament-panels::page>
    <div class="space-y-7">
        @include('filament.pages.home.ap-processor.back-on-desk')
        @include('filament.pages.home.ap-processor.kpis')
        @include('filament.pages.home.ap-processor.work-queue')

        <div class="grid gap-6 lg:grid-cols-2">
            @include('filament.pages.home.ap-processor.queue-aging')
            @include('filament.pages.home.ap-processor.keeping-up')
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            @include('filament.pages.home.ap-processor.escalations')
            @include('filament.pages.home.ap-processor.tax-summary')
        </div>

        @include('filament.pages.home.ap-processor.insights')
    </div>

    <x-user-guide :steps="$guide" />
</x-filament-panels::page>
