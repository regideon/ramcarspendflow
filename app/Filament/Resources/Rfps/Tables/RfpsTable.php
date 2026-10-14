<?php

namespace App\Filament\Resources\Rfps\Tables;

use App\Enums\RfpRequestType;
use App\Enums\RfpStatus;
use App\Filament\Actions\OpenCommentsAction;
use App\Filament\Resources\Rfps\RfpResource;
use App\Models\Rfp\Rfp;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RfpsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['company', 'dueType', 'vendor', 'requestor', 'items.currency']))
            ->columns([
                TextColumn::make('rfp_number')
                    ->label('RFP no.')
                    // The search bar also finds RFPs by the invoice number of any line
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('rfp_number', 'like', "%{$search}%")
                        ->orWhereHas('items', fn (Builder $query) => $query->where('invoice_number', 'like', "%{$search}%")))
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('company.short_name')
                    ->label('Company'),
                TextColumn::make('request_type')
                    ->label('Type')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('dueType.name')
                    ->label('Due type')
                    ->badge()
                    ->color(fn (Rfp $record): string => $record->dueType?->code === 'urgent' ? 'danger' : 'gray'),
                TextColumn::make('vendor.name')
                    ->label('Payee')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn (Rfp $record): ?string => $record->vendor?->name),
                TextColumn::make('total_php_amount')
                    ->label('Amount (PHP)')
                    ->numeric(decimalPlaces: 2)
                    ->description(fn (Rfp $record): ?string => collect($record->foreignTotals())
                        ->map(fn (string $amount, string $code): string => $code.' '.number_format((float) $amount, 2))
                        ->implode(' · ') ?: null)
                    ->alignEnd()
                    ->sortable(),
                IconColumn::make('is_direct_to_vp')
                    ->label('VP')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('requestor.name')
                    ->label('Requestor')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Last update')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(RfpStatus::class)
                    ->multiple(),
                SelectFilter::make('request_type')
                    ->label('RFP type')
                    ->options(RfpRequestType::class),
                SelectFilter::make('rfp_due_type_id')
                    ->label('Due type')
                    ->relationship('dueType', 'name'),
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->relationship('company', 'name'),
            ])
            ->recordActions([
                OpenCommentsAction::make(),
                ViewAction::make()->iconButton(),
                EditAction::make()
                    ->iconButton()
                    ->visible(fn (Rfp $record): bool => RfpResource::canEdit($record)),
            ])
            ->recordUrl(fn (Rfp $record): string => RfpResource::getUrl('view', ['record' => $record]))
            ->searchPlaceholder('Search RFP no., invoice no. or payee')
            ->striped();
    }
}
