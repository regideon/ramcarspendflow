<?php

namespace App\Filament\Resources\P2p\Invoices\Tables;

use App\Enums\P2pStatus;
use App\Filament\Resources\P2p\Invoices\InvoiceResource;
use App\Models\P2p\Invoice;
use Filament\Actions\ViewAction;
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
                TextColumn::make('company.short_name')
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
                ViewAction::make()->iconButton(),
            ])
            ->recordUrl(fn (Invoice $record): string => InvoiceResource::getUrl('view', ['record' => $record]))
            ->searchPlaceholder('Search GRC no., invoice no. or vendor')
            ->striped();
    }
}
