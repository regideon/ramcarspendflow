<?php

namespace App\Livewire\P2p;

use App\Enums\RoleName;
use App\Filament\Pages\P2p\BulkAccountsPayable;
use App\Filament\Pages\P2p\BulkGoodsReceipt;
use App\Filament\Resources\P2p\Invoices\Schemas\InvoiceRecordPanel;
use App\Models\P2p\Invoice;
use App\Services\P2p\P2pInvoicePanelEditor;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The right-hand record pane for the P2P request split view.
 *
 * It is a separate Livewire component so selecting a row re-renders only the
 * pane: the table, its scroll position and the dragged split width stay put.
 */
class InvoiceRecordPane extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public ?int $invoiceId = null;

    public bool $isEditing = false;

    /**
     * @var array<string, mixed>
     */
    public array $recordData = [];

    protected ?Invoice $invoice = null;

    protected bool $invoiceLoaded = false;

    public function mount(?int $invoiceId = null): void
    {
        $this->invoiceId = $invoiceId;
        $this->loadInvoice();
    }

    #[On('p2p-record-selected')]
    public function select(int $id): void
    {
        $this->invoiceId = $id;
        $this->isEditing = false;
        $this->recordData = [];
        $this->invoiceLoaded = false;
        $this->loadInvoice();

        if ($this->invoice === null) {
            Notification::make()
                ->title('Request not available')
                ->body('This P2P request is not visible to your account.')
                ->warning()
                ->send();

            $this->close();
        }
    }

    public function clear(): void
    {
        $this->invoiceId = null;
        $this->invoice = null;
        $this->invoiceLoaded = true;
        $this->isEditing = false;
        $this->recordData = [];
    }

    public function close(): void
    {
        $this->clear();
        $this->dispatch('p2p-record-closed');
    }

    public function getInvoice(): ?Invoice
    {
        if (! $this->invoiceLoaded) {
            $this->loadInvoice();
        }

        return $this->invoice;
    }

    public function canEdit(): bool
    {
        $invoice = $this->getInvoice();

        return $invoice !== null
            && app(P2pInvoicePanelEditor::class)->canEdit($invoice, auth()->user());
    }

    public function startEditing(): void
    {
        $invoice = $this->getInvoice();

        if ($invoice === null || ! $this->canEdit()) {
            return;
        }

        $this->recordData = app(P2pInvoicePanelEditor::class)->dataFor($invoice);
        $this->isEditing = true;
    }

    public function cancelEditing(): void
    {
        $this->isEditing = false;
        $this->recordData = [];
    }

    public function save(): void
    {
        $invoice = $this->getInvoice();

        if ($invoice === null || ! $this->canEdit()) {
            return;
        }

        // Rules are on the schema components, so errors render next to the fields.
        $this->recordEdit->validate();

        app(P2pInvoicePanelEditor::class)->update($invoice, $this->recordData);

        $this->isEditing = false;
        $this->recordData = [];
        $this->invoiceLoaded = false;
        $this->loadInvoice();

        Notification::make()
            ->title('Request updated')
            ->success()
            ->send();
    }

    public function recordEdit(Schema $schema): Schema
    {
        $invoice = $this->getInvoice();

        if (! $invoice instanceof Invoice) {
            return $schema;
        }

        return InvoiceRecordPanel::edit($schema, $invoice);
    }

    public function reviewUrl(): ?string
    {
        $invoice = $this->getInvoice();
        $user = auth()->user();

        if ($invoice === null) {
            return null;
        }

        if ($user?->hasAnyRole([RoleName::ApProcessor, RoleName::ApSupervisor, RoleName::Admin])) {
            return BulkAccountsPayable::getUrl(['invoice' => $invoice->id]);
        }

        if ($user?->hasRole(RoleName::Vendor)) {
            return BulkGoodsReceipt::getUrl(['invoice' => $invoice->id]);
        }

        return null;
    }

    public function reviewLabel(): string
    {
        return auth()->user()?->hasRole(RoleName::Vendor) ? 'Open process screen' : 'Open review screen';
    }

    protected function loadInvoice(): void
    {
        $this->invoice = null;
        $this->invoiceLoaded = true;

        $user = auth()->user();

        if ($this->invoiceId === null || $user === null) {
            return;
        }

        $this->invoice = Invoice::query()
            ->visibleTo($user)
            ->with([
                'vendor',
                'company',
                'lines.sapItem.goodReceipt',
                'histories.actionedBy',
                'escalations.reason.steps',
            ])
            ->find($this->invoiceId);
    }
}
