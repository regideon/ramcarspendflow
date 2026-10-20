<?php

namespace App\Filament\Pages;

use App\Enums\RfpRequestType;
use App\Enums\RoleName;
use App\Filament\Concerns\HasCommentsChat;
use App\Filament\Resources\P2p\Approvals\ApprovalResource;
use App\Filament\Resources\P2p\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Resources\P2p\PurchaseOrders\Actions\SyncFromSapAction;
use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Filament\Resources\Rfps\RfpResource;
use App\Filament\Widgets\Accounting\ApProcessor;
use App\Filament\Widgets\P2p\Vendor;
use App\Filament\Widgets\Rfp\Requestor;
use App\Services\Accounting\ApProcessorDashboard;
use App\Services\P2p\P2pVendorDashboard;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Home page: one dashboard per role (docs/dashboard-plan.md §4).
 * Roles without their own dashboard yet get the panel's default widgets.
 */
class Dashboard extends BaseDashboard
{
    use HasCommentsChat;

    public function getWidgets(): array
    {
        return match ($this->role()) {
            RoleName::Vendor => [
                Vendor\ActionNeeded::class,
                Vendor\VendorStats::class,
                Vendor\ReadyToBill::class,
                Vendor\PaymentOutlook::class,
                Vendor\MyInvoices::class,
                Vendor\Escalations::class,
                Vendor\Turnaround::class,
                Vendor\Insights::class,
            ],
            RoleName::ApProcessor => [
                ApProcessor\BackOnDesk::class,
                ApProcessor\ApProcessorStats::class,
                ApProcessor\WorkQueue::class,
                ApProcessor\QueueAging::class,
                ApProcessor\KeepingUp::class,
                ApProcessor\EscalationsRaised::class,
                ApProcessor\TaxSummary::class,
                ApProcessor\Insights::class,
            ],
            RoleName::Requestor => [
                Requestor\ActionNeeded::class,
                Requestor\RequestorStats::class,
                Requestor\RequestsInFlight::class,
                Requestor\Pipeline::class,
                Requestor\StageWait::class,
                Requestor\SpendOverTime::class,
                Requestor\UpcomingDue::class,
                Requestor\Insights::class,
            ],
            default => parent::getWidgets(),
        };
    }

    public function getColumns(): int|array
    {
        // The vendor layout splits a row 60/40 and 50/50, so it needs a 10-column grid.
        return $this->role() === RoleName::Vendor ? ['md' => 2, 'xl' => 10] : ['md' => 2];
    }

    public function getHeading(): string
    {
        if ($this->role() === RoleName::Vendor) {
            return 'Welcome back, '.$this->vendorDashboard()->vendorName();
        }

        $greeting = match (true) {
            now()->hour < 12 => 'Good morning',
            now()->hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        return $greeting.', '.str(auth()->user()->name)->before(' ');
    }

    public function getSubheading(): ?string
    {
        return match ($this->role()) {
            RoleName::Vendor => 'Bill your deliveries and track payment status. Last synced from SAP: '
                .$this->vendorDashboard()->lastSyncedAt()->calendar().'.',
            RoleName::ApProcessor => $this->apProcessorSubheading(),
            RoleName::Requestor => "Here's where your payment requests stand.",
            default => null,
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make(collect(RfpRequestType::cases())->map(
                fn (RfpRequestType $type): Action => Action::make('new'.$type->name)
                    ->label($type->getLabel())
                    ->url(RfpResource::getUrl('create', ['type' => $type->value])),
            )->all())
                ->label('New RFP')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->button()
                ->visible(fn (): bool => $this->role() === RoleName::Requestor && RfpResource::canCreate()),

            Action::make('openRfpQueue')
                ->label('Open RFP queue')
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('gray')
                ->url(fn (): string => RfpApprovalResource::getUrl('index'))
                ->visible(fn (): bool => $this->role() === RoleName::ApProcessor),

            Action::make('processP2pInvoices')
                ->label('Process P2P invoices')
                ->icon(Heroicon::OutlinedInboxArrowDown)
                ->url(fn (): string => ApprovalResource::getUrl('index'))
                ->visible(fn (): bool => $this->role() === RoleName::ApProcessor),

            SyncFromSapAction::make()
                ->color('gray'),

            Action::make('billGoodsReceipts')
                ->label('Bill goods receipts')
                ->icon(Heroicon::OutlinedTruck)
                ->url(fn (): string => GoodsReceiptResource::getUrl('index'))
                ->visible(fn (): bool => $this->role() === RoleName::Vendor),
        ];
    }

    /**
     * The role whose dashboard this user sees, first match wins: an AP Processor who
     * also raises RFPs gets the AP dashboard.
     */
    protected function role(): ?RoleName
    {
        $user = auth()->user();

        foreach ([RoleName::Vendor, RoleName::ApProcessor, RoleName::Requestor] as $role) {
            if ($user?->hasRole($role)) {
                return $role;
            }
        }

        return null;
    }

    protected function apProcessorSubheading(): string
    {
        ['total' => $total, 'overdue' => $overdue] = (new ApProcessorDashboard(auth()->user()))->queueSize();

        return "{$total} ".str('item')->plural($total).' in your queue'.($overdue ? " · {$overdue} overdue" : '').'.';
    }

    protected function vendorDashboard(): P2pVendorDashboard
    {
        return new P2pVendorDashboard(auth()->user());
    }
}
