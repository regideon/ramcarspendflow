{{-- P2P Approvals, redesigned from Google Stitch. The old list stays at P2P Approvals (OLD). --}}
<x-filament-panels::page>
    <div class="space-y-4 pb-16">
        @include('filament.pages.p2p.approval-queue.toolbar')
        @include('filament.pages.p2p.approval-queue.table')
    </div>

    <x-user-guide :steps="$this->guideSteps()" />
</x-filament-panels::page>
