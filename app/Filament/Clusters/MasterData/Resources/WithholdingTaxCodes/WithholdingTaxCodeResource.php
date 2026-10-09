<?php

namespace App\Filament\Clusters\MasterData\Resources\WithholdingTaxCodes;

use App\Filament\Clusters\MasterData\MasterDataCluster;
use App\Filament\Clusters\MasterData\Resources\WithholdingTaxCodes\Pages\ManageWithholdingTaxCodes;
use App\Filament\Concerns\RestrictedToAdmins;
use App\Models\WithholdingTaxCode;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class WithholdingTaxCodeResource extends Resource
{
    use RestrictedToAdmins;

    protected static ?string $model = WithholdingTaxCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $cluster = MasterDataCluster::class;

    protected static ?int $navigationSort = 11;

    protected static ?string $navigationLabel = 'WHT Codes';

    protected static ?string $modelLabel = 'withholding tax code';

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('withholding_tax_type_id')
                    ->label('Type')
                    ->relationship('withholdingTaxType', 'name')
                    ->live(),
                TextInput::make('code')
                    ->label('WHT code')
                    ->required()
                    ->maxLength(2)
                    ->helperText('SAP withholding tax code (WT_WITHCD)')
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('withholding_tax_type_id', $get('withholding_tax_type_id'))),
                TextInput::make('atc')
                    ->label('ATC')
                    ->maxLength(10)
                    ->helperText('BIR alphanumeric tax code, e.g. WC010'),
                TextInput::make('description')
                    ->required()
                    ->maxLength(150),
                TextInput::make('rate')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%')
                    ->default(0),
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
                TextColumn::make('atc')
                    ->label('ATC')
                    ->searchable(),
                TextColumn::make('description')
                    ->searchable(),
                TextColumn::make('rate')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('withholdingTaxType.name')
                    ->label('Type')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('code')
            ->filters([
                SelectFilter::make('withholding_tax_type_id')
                    ->label('Type')
                    ->relationship('withholdingTaxType', 'name'),
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
            'index' => ManageWithholdingTaxCodes::route('/'),
        ];
    }
}
