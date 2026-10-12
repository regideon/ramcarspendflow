<?php

namespace App\Filament\Resources\P2p\Invoices\Tables;

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Pages\P2p\BulkGoodsReceipt;
use App\Filament\Resources\P2p\Invoices\InvoiceResource;
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
                TextColumn::make('grc_number')
                    ->label('GRC no.')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('company.name')
                    ->label('Company'),
                TextColumn::make('invoice_number')
                    ->label('Invoice no.')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('amount_submitted')
                    ->label('Amount')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
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
                    ->url(fn (Invoice $record): string => self::viewUrl($record)),
            ])
            ->recordUrl(fn (Invoice $record): string => self::viewUrl($record))
            ->searchPlaceholder('Search GRC no., invoice no. or vendor')
            ->striped();
    }

    /**
     * Vendors and admins open a submission in the bulk goods-receipt screen (read-only
     * unless the invoice was returned to the vendor). AP processors cannot access that
     * page, so they keep the plain view page.
     */
    private static function viewUrl(Invoice $record): string
    {
        if (auth()->user()?->hasAnyRole([RoleName::Vendor, RoleName::Admin])) {
            return BulkGoodsReceipt::getUrl(['invoice' => $record->id]);
        }

        return InvoiceResource::getUrl('view', ['record' => $record]);
    }
}
