{{-- My requests, redesigned from Google Stitch (docs/stitch/hub-rfps/). --}}
<x-filament-panels::page>
    <div class="space-y-6 pb-16">
        @include('filament.pages.rfp.my-requests.search')
        @include('filament.pages.rfp.my-requests.chips')
        @include('filament.pages.rfp.my-requests.table')
    </div>

    @include('filament.pages.rfp.my-requests.bulk-bar')
    @include('filament.pages.rfp.my-requests.drawer')

    <x-user-guide :steps="$this->guideSteps()" />
</x-filament-panels::page>
