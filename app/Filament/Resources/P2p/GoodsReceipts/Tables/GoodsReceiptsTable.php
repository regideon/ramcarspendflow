<?php

namespace App\Filament\Resources\P2p\GoodsReceipts\Tables;

use App\Enums\RoleName;
use App\Filament\Pages\P2p\BulkGoodsReceipt;
use App\Models\P2p\GoodReceiptState;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
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
                    ->alignEnd()
                    // Vendors do not see pricing on their own goods receipts.
                    ->visible(fn (): bool => ! (auth()->user()?->hasRole(RoleName::Vendor) ?? false)),
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
            ])
            ->toolbarActions([
                BulkAction::make('processSelected')
                    ->label('Process Selected')
                    ->icon(Heroicon::OutlinedPlay)
                    ->visible(fn (): bool => auth()->user()?->hasRole(RoleName::Vendor) ?? false)
                    ->action(fn (Collection $records) => redirect(BulkGoodsReceipt::getUrl(['ids' => $records->pluck('id')->all()]))),
            ])
            ->recordUrl(fn (GoodReceiptState $record): ?string => self::processUrl($record))
            ->striped();
    }

    /**
     * A row opens the bulk goods-receipt screen, which shows the receipt read-only
     * unless it is pending (then the vendor can process it).
     */
    protected static function processUrl(GoodReceiptState $record): ?string
    {
        return BulkGoodsReceipt::getUrl(['ids' => [$record->id]]);
    }
}
