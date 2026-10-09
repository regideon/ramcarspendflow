<?php

namespace App\Filament\Clusters\MasterData\Resources\WithholdingTaxTypes;

use App\Filament\Clusters\MasterData\MasterDataCluster;
use App\Filament\Clusters\MasterData\Resources\WithholdingTaxTypes\Pages\ManageWithholdingTaxTypes;
use App\Filament\Concerns\RestrictedToAdmins;
use App\Models\WithholdingTaxType;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class WithholdingTaxTypeResource extends Resource
{
    use RestrictedToAdmins;

    protected static ?string $model = WithholdingTaxType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $cluster = MasterDataCluster::class;

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'WHT Types';

    protected static ?string $modelLabel = 'withholding tax type';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Type')
                    ->required()
                    ->maxLength(2)
                    ->unique(ignoreRecord: true)
                    ->helperText('SAP withholding tax type (WITHT)'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(150),
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
                    ->label('Type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('code')
            ->filters([
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
            'index' => ManageWithholdingTaxTypes::route('/'),
        ];
    }
}
