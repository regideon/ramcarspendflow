<?php

namespace App\Filament\Clusters\RfpSettings\Resources\Categories\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class SubcategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'subcategories';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(150)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('rfp_category_id', $this->getOwnerRecord()->getKey())),
                Select::make('gl_account_id')
                    ->label('G/L account')
                    ->relationship('glAccount', 'description')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->code} - {$record->description}")
                    ->searchable(['code', 'description'])
                    ->required(),
                TextInput::make('document_type')
                    ->helperText('SAP document type (BLART)')
                    ->required()
                    ->length(2)
                    ->default('KR'),
                TextInput::make('posting_key')
                    ->helperText('SAP posting key (BSCHL)')
                    ->required()
                    ->length(2)
                    ->default('40'),
                Toggle::make('requires_cost_center')
                    ->label('Requires cost center'),
                Toggle::make('requires_po_number')
                    ->label('Requires PO number'),
                Toggle::make('is_default')
                    ->label('Default')
                    ->helperText('Pre-selected on new RFP lines.'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('glAccount.code')
                    ->label('G/L account')
                    ->searchable(),
                TextColumn::make('document_type')
                    ->label('Doc. type'),
                TextColumn::make('posting_key'),
                IconColumn::make('requires_cost_center')
                    ->label('Cost center')
                    ->boolean(),
                IconColumn::make('requires_po_number')
                    ->label('PO no.')
                    ->boolean(),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
