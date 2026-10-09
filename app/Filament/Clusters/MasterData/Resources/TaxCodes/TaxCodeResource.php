<?php

namespace App\Filament\Clusters\MasterData\Resources\TaxCodes;

use App\Enums\TaxCodeCategory;
use App\Filament\Clusters\MasterData\MasterDataCluster;
use App\Filament\Clusters\MasterData\Resources\TaxCodes\Pages\ManageTaxCodes;
use App\Filament\Concerns\RestrictedToAdmins;
use App\Models\TaxCode;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TaxCodeResource extends Resource
{
    use RestrictedToAdmins;

    protected static ?string $model = TaxCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $cluster = MasterDataCluster::class;

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'VAT Codes';

    protected static ?string $modelLabel = 'VAT code';

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Tax code')
                    ->required()
                    ->maxLength(2)
                    ->unique(ignoreRecord: true)
                    ->helperText('SAP tax code (MWSKZ)'),
                TextInput::make('description')
                    ->required()
                    ->maxLength(150),
                Select::make('category')
                    ->options(TaxCodeCategory::class)
                    ->default(TaxCodeCategory::Taxable)
                    ->required(),
                TextInput::make('rate')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%')
                    ->default(0),
                TextInput::make('tax_procedure')
                    ->required()
                    ->maxLength(10)
                    ->default('TAXPH'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->searchable(),
                TextColumn::make('category')
                    ->badge(),
                TextColumn::make('rate')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('code')
            ->filters([
                SelectFilter::make('category')
                    ->options(TaxCodeCategory::class),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTaxCodes::route('/'),
        ];
    }
}
