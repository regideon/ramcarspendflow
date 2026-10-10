<?php

namespace App\Filament\Resources\P2p\PurchaseOrders\Tables;

use App\Enums\P2pPurchaseOrderStatus;
use App\Filament\Resources\P2p\PurchaseOrders\PurchaseOrderResource;
use App\Models\P2p\SapPurchaseOrder;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('goodReceipts'))
            ->columns([
                TextColumn::make('purchase_order')
                    ->label('PO no.')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('vendor_code')
                    ->label('Vendor')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('company_code')
                    ->label('Company')
                    ->sortable(),
                TextColumn::make('status_id')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->numeric(decimalPlaces: 2)
                    ->description(fn (SapPurchaseOrder $record): ?string => $record->currency_key)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('gr_numbers')
                    ->label('GRs')
                    ->state(fn (SapPurchaseOrder $record): int => count($record->gr_numbers ?? []))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('purchase_order_date')
                    ->label('PO date')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('changed_on')
                    ->label('Last change')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('changed_on', 'desc')
            ->filters([
                SelectFilter::make('status_id')
                    ->label('Status')
                    ->options(P2pPurchaseOrderStatus::class),
            ])
            ->recordActions([
                ViewAction::make()->iconButton(),
            ])
            ->recordUrl(fn (SapPurchaseOrder $record): string => PurchaseOrderResource::getUrl('view', ['record' => $record]))
            ->searchPlaceholder('Search PO no. or vendor')
            ->striped();
    }
}
