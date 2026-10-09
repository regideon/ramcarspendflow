<?php

namespace App\Filament\Clusters\MasterData\Resources\Vendors\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class VendorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Vendor no.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tax_number')
                    ->label('TIN')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('accountGroup.description')
                    ->label('Account group')
                    ->toggleable(),
                TextColumn::make('companies.short_name')
                    ->label('Companies')
                    ->badge(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('companies')
                    ->relationship('companies', 'name')
                    ->multiple(),
                SelectFilter::make('account_group_id')
                    ->label('Account group')
                    ->relationship('accountGroup', 'description'),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
