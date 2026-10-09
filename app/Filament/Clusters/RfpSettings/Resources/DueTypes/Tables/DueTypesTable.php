<?php

namespace App\Filament\Clusters\RfpSettings\Resources\DueTypes\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DueTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->description(fn ($record): ?string => $record->description)
                    ->searchable(),
                TextColumn::make('code')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('processing_days')
                    ->label('Days')
                    ->numeric()
                    ->suffix(fn ($record): string => $record->business_days_only ? ' business' : ' calendar'),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('rfps_count')
                    ->label('RFPs')
                    ->counts('rfps')
                    ->numeric(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
