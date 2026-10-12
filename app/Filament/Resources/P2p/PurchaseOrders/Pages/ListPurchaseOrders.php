<?php

namespace App\Filament\Resources\P2p\PurchaseOrders\Pages;

use App\Enums\RoleName;
use App\Filament\Resources\P2p\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\P2p\PurchaseOrders\Tables\PurchaseOrdersTable;
use App\Jobs\P2p\SyncP2pPurchaseOrders;
use App\Models\P2p\SapPurchaseOrder;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncFromSap')
                ->label('Sync from SAP')
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible(fn (): bool => auth()->user()?->hasRole(RoleName::Vendor) ?? false)
                ->action(function (): void {
                    $user = auth()->user();

                    $queued = SyncP2pPurchaseOrders::dispatchUnlessPending([
                        'vendor' => $user->vendor?->code,
                        'changed_from' => now()->subDays(3)->format('Ymd'),
                        'changed_to' => now()->format('Ymd'),
                    ], $user->id, 'user-sync');

                    Notification::make()
                        ->title($queued ? 'Sync started' : 'Sync already in progress')
                        ->{$queued ? 'success' : 'warning'}()
                        ->send();
                }),
        ];
    }

    public function getTabs(): array
    {
        $user = auth()->user();
        $vendorCode = $user->vendor->code ?? null;

        // ->toBase() skips Eloquent model hydration for a massive speed boost
        $counts = SapPurchaseOrder::query()
            ->whereNotNull('gr_numbers')
            ->when($vendorCode, fn (Builder $q) => $q->where('vendor_code', $vendorCode))
            // ->when(ActiveCompany::code(), fn (Builder $q, string $code) => $q->where('company_code', $code)) // TODO: Service provider for active company code
            // Match the table's company scoping so the tab badges agree with the rows.
            // ->when( // TODO: ONSELECT OF COMPANY CODES, THIS IS THE PLACE TO FILTER
            //     filled($companyCodes = PurchaseOrdersTable::userCompanyCodes($user)),
            //     fn (Builder $q) => $q->whereIn('company_code', $companyCodes)
            // )
            ->toBase()
            ->selectRaw('
                COUNT(*) as all_count,
                COUNT(CASE WHEN status_id IN (0, 2) THEN 1 END) as pending_partial,
                COUNT(CASE WHEN status_id = 3 THEN 1 END) as fully_submitted,
                COUNT(CASE WHEN status_id = 1 THEN 1 END) as completed
            ')
            ->first();

        return [
            'all' => Tab::make('All')
                ->badge($counts->all_count ?? 0),

            'pending_and_partial' => Tab::make('Pending & Partially Submitted')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status_id', [0, 2]))
                ->badge($counts->pending_partial ?? 0)
                ->badgeColor('warning'),

            'fully_submitted' => Tab::make('Fully Submitted')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status_id', 3))
                ->badge($counts->fully_submitted ?? 0)
                ->badgeColor('info'),

            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status_id', 1))
                ->badge($counts->completed ?? 0)
                ->badgeColor('success'),
        ];
    }
}
