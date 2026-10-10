<?php

namespace App\Filament\Resources\P2p\GoodsReceipts\Tables;

use App\Enums\RoleName;
use App\Filament\Pages\P2p\BulkGoodsReceipt;
use App\Filament\Resources\P2p\GoodsReceipts\GoodsReceiptResource;
use App\Models\P2p\GoodReceiptState;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class GoodsReceiptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['vendor', 'company']))
            ->columns([
                TextColumn::make('good_receipt_number')
                    ->label('GR no.')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('purchase_order')
                    ->label('PO no.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('amount_total')
                    ->label('Amount')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                Action::make('process')
                    ->icon(Heroicon::OutlinedPlay)
                    ->color('primary')
                    ->visible(fn (GoodReceiptState $record): bool => $record->status->isEditableByVendor()
                        && (auth()->user()?->hasRole(RoleName::Vendor) ?? false))
                    ->url(fn (GoodReceiptState $record): string => BulkGoodsReceipt::getUrl(['ids' => [$record->id]])),
                ViewAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkAction::make('processSelected')
                    ->label('Process Selected')
                    ->icon(Heroicon::OutlinedPlay)
                    ->visible(fn (): bool => auth()->user()?->hasRole(RoleName::Vendor) ?? false)
                    ->action(fn (Collection $records) => redirect(BulkGoodsReceipt::getUrl(['ids' => $records->pluck('id')->all()]))),
            ])
            ->recordUrl(fn (GoodReceiptState $record): string => GoodsReceiptResource::getUrl('view', ['record' => $record]))
            ->striped();
    }
}
