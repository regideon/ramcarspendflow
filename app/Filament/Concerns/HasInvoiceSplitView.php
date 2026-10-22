<?php

namespace App\Filament\Concerns;

use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Url;

/**
 * Table + record split view for P2P requests (invoices).
 *
 * The table stays live on the left. Selecting a row is intentionally renderless:
 * the page (and therefore the table's scroll position and the dragged split
 * width) stays put, while the nested `p2p.invoice-record-pane` component reloads
 * its own record.
 */
trait HasInvoiceSplitView
{
    #[Url(as: 'record', except: null)]
    public ?int $selectedRecordId = null;

    #[Renderless]
    public function selectRecord(int $id): void
    {
        $this->selectedRecordId = $id;

        $this->dispatch('p2p-record-selected', id: $id);
    }

    #[On('p2p-record-closed')]
    #[Renderless]
    public function clearSelectedRecord(): void
    {
        $this->selectedRecordId = null;
    }
}
