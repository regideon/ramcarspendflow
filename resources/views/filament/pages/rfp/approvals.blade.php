{{-- RFP Approvals, redesigned from Google Stitch. --}}
<x-filament-panels::page>
    <div class="space-y-4 pb-16">
        @include('filament.pages.rfp.approvals.chips')

        <section
            class="sf-card flex flex-col overflow-hidden"
            x-data="{
                hidden: (() => { try { return JSON.parse(localStorage.getItem('sf.rfpApprovals.hidden') ?? '[]') } catch (e) { return [] } })(),
                shows(column) { return ! this.hidden.includes(column) },
                toggle(column) {
                    this.hidden = this.shows(column) ? [...this.hidden, column] : this.hidden.filter((c) => c !== column)
                    try { localStorage.setItem('sf.rfpApprovals.hidden', JSON.stringify(this.hidden)) } catch (e) {}
                },
            }"
        >
            @include('filament.pages.rfp.approvals.tabs')
            @include('filament.pages.rfp.approvals.toolbar')
            @include('filament.pages.rfp.approvals.table')
        </section>
    </div>

    <x-user-guide :steps="$this->guideSteps()" />
</x-filament-panels::page>
