<?php

namespace App\Filament\Resources\P2p\PurchaseOrders\Tables;

use App\Enums\P2pPurchaseOrderStatus;
use App\Enums\RoleName;
use App\Filament\Resources\P2p\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Resources\P2p\PurchaseOrders\PurchaseOrderResource;
use App\Models\P2p\SapPurchaseOrder;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('goodReceipts', 'company'))
            ->columns([
                TextColumn::make('purchase_order')
                    ->label('PO Number')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('gr_numbers')
                    ->label('GRs')
                    ->state(fn (SapPurchaseOrder $record): int => implode(', ', $record->gr_numbers ?? [])),
                TextColumn::make('vendor_code')
                    ->label('Vendor')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: (fn ($record): bool => auth()->user()?->hasRole(RoleName::Vendor) ?? false)),
                TextColumn::make('company.name')
                    ->label('Company')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: (fn ($record): bool => ! (auth()->user()?->hasRole(RoleName::Vendor) ?? false))),
                TextColumn::make('status_id')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                // TextColumn::make('total_amount')
                //     ->label('Amount')
                //     ->numeric(decimalPlaces: 2)
                //     ->prefix(fn (SapPurchaseOrder $record): ?string => $record->currency_key . ' ')
                //     ->alignEnd()
                //     ->sortable(),
                TextColumn::make('purchase_order_date')
                    ->label('PO date')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('quantity_po_balance')
                    ->label('PO balance')
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),

                // TextColumn::make('changed_on')
                //     ->label('Last change')
                //     ->date()
                //     ->sortable(),
            ])
            ->defaultSort('changed_on', 'desc')
            ->filters([
                SelectFilter::make('status_id')
                    ->label('Status')
                    ->options(P2pPurchaseOrderStatus::class),
            ])
            ->recordActions([
                Action::make('viewGrs')
                    ->label("View GR's")
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->url(fn (SapPurchaseOrder $record): string => GoodsReceiptResource::getUrl('index', [
                        'purchase_orders' => [$record->purchase_order],
                    ])),
            ])
            ->toolbarActions([
                BulkAction::make('processGrs')
                    ->label('Process GRs')
                    ->icon(Heroicon::OutlinedPlay)
                    ->visible(fn (): bool => auth()->user()?->hasRole(RoleName::Vendor) ?? false)
                    ->action(fn (Collection $records) => redirect(GoodsReceiptResource::getUrl('index', [
                        'purchase_orders' => $records->pluck('purchase_order')->all(),
                    ]))),
            ])
            ->recordUrl(fn (SapPurchaseOrder $record): string => PurchaseOrderResource::getUrl('view', ['record' => $record]))
            ->searchPlaceholder('Search PO no. or vendor')
            ->striped();
    }
}
