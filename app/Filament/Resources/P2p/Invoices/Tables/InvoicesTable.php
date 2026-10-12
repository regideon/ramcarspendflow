<?php

namespace App\Filament\Resources\P2p\Invoices\Tables;

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Pages\P2p\BulkGoodsReceipt;
use App\Models\P2p\Invoice;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['vendor', 'company']))
            ->columns([
                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable()
                    ->limit(30)
                    ->description(fn (Invoice $record): ?string => $record->vendor?->code)
                    ->hidden(fn (): bool => auth()->user()?->hasRole(RoleName::Vendor) ?? false),

                TextColumn::make('company.name')
                    ->label('Company')
                    ->hidden(fn (): bool => ! (auth()->user()?->hasRole(RoleName::Vendor) ?? false)),

                TextColumn::make('po_numbers')
                    ->label('PO')
                    ->listWithLineBreaks()
                    ->searchable(),

                TextColumn::make('reference_number')
                    ->label('Reference')
                    ->searchable(),

                TextColumn::make('invoice_at')
                    ->label('Invoice Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('gr_numbers')
                    ->label('GR')
                    ->listWithLineBreaks()
                    ->searchable(),

                TextColumn::make('amount_submitted')
                    ->label('Amount')
                    ->numeric(decimalPlaces: 2, thousandsSeparator: ',')
                    ->alignEnd()
                    ->sortable()
                    ->hidden(fn (): bool => auth()->user()?->hasRole(RoleName::Vendor) ?? false),

                TextColumn::make('invoice_number')
                    ->label('Invoice No')
                    // SAP fills this in only after the invoice is parked/posted.
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(P2pStatus::class)
                    ->multiple(),
            ])
            ->recordActions([
                Action::make('resubmit')
                    ->label('Resubmit')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (Invoice $record): bool => $record->status === P2pStatus::ReturnedToVendor
                        && (auth()->user()?->hasRole(RoleName::Vendor) ?? false))
                    ->url(fn (Invoice $record): string => BulkGoodsReceipt::getUrl(['invoice' => $record->id])),
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::Eye)
                    ->color('gray')
                    ->iconButton()
                    ->visible(fn (Invoice $record): bool => self::viewUrl($record) !== null)
                    ->url(fn (Invoice $record): ?string => self::viewUrl($record)),
            ])
            ->recordUrl(fn (Invoice $record): ?string => self::viewUrl($record))
            ->searchPlaceholder('Search GRC no., invoice no. or vendor')
            ->striped();
    }

    /**
     * Vendors and admins open a submission in the bulk goods-receipt screen (read-only
     * unless the invoice was returned to the vendor). AP processors have no detail
     * screen, so their rows stay unlinked.
     */
    private static function viewUrl(Invoice $record): ?string
    {
        if (auth()->user()?->hasAnyRole([RoleName::Vendor, RoleName::Admin])) {
            return BulkGoodsReceipt::getUrl(['invoice' => $record->id]);
        }

        return null;
    }
}
