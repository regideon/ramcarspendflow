<?php

namespace App\Filament\Resources\P2p\Approvals\Pages;

use App\Enums\P2pStatus;
use App\Filament\Concerns\HasCommentsChat;
use App\Filament\Resources\P2p\Approvals\ApprovalResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListApprovals extends ListRecords
{
    use HasCommentsChat;

    protected static string $resource = ApprovalResource::class;

    public function mount(): void
    {
        parent::mount();

        // Filament binds the tab to `?tab=`, but P2P deep links (like the GR list)
        // use `?activeTab=`. Accept both so either link lands on the right queue.
        if (is_string($activeTab = request()->query('activeTab')) && filled($activeTab)) {
            $this->activeTab = $activeTab;
        }
    }

    public function getDefaultActiveTab(): string
    {
        return 'mine';
    }

    /**
     * The AP queues. Only "For my action" is the viewer's own worklist (the AP
     * Processor receives submissions submitted to AP; the AP Supervisor — the
     * legacy checker — receives those forwarded to the checker). The other tabs
     * are read-only overviews.
     */
    public function getTabs(): array
    {
        return [
            'mine' => Tab::make('For my action')
                ->icon(Heroicon::User)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->actionableBy(auth()->user())),

            'all' => Tab::make('All')
                ->icon(Heroicon::Banknotes),

            'escalated' => Tab::make('Escalated')
                ->icon(Heroicon::ExclamationCircle)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    P2pStatus::EscalatedByVendor->value,
                    P2pStatus::EscalatedByProcessor->value,
                    P2pStatus::EscalateToProcurement->value,
                    P2pStatus::EscalatedToWarehouse->value,
                    P2pStatus::PartiallyEscalated->value,
                ])),

            'ap_processor' => Tab::make('AP Processor')
                ->icon(Heroicon::QueueList)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->actionableByApProcessor()),

            'ap_checker' => Tab::make('AP Checker')
                ->icon(Heroicon::QueueList)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->actionableByApSupervisor()),

            'posted' => Tab::make('Posted to SAP')
                ->icon(Heroicon::CheckCircle)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    P2pStatus::InvoiceParked->value,
                    P2pStatus::InvoicePosted->value,
                    P2pStatus::Completed->value,
                ])),
        ];
    }
}
