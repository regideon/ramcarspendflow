<?php

namespace App\Filament\Clusters\P2pSettings\Resources\SlocClassifications;

use App\Filament\Clusters\P2pSettings\P2pSettingsCluster;
use App\Filament\Clusters\P2pSettings\Resources\SlocClassifications\Pages\ManageSlocClassifications;
use App\Filament\Concerns\RestrictedToAdmins;
use App\Models\P2p\SlocClassification;
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
use Filament\Tables\Table;

class SlocClassificationResource extends Resource
{
    use RestrictedToAdmins;

    protected static ?string $model = SlocClassification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $cluster = P2pSettingsCluster::class;

    protected static ?string $navigationLabel = 'SLOC Classification';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('company_code')
                ->label('Company code')
                ->maxLength(10)
                ->helperText('Leave blank for a global default.'),
            TextInput::make('plant')
                ->maxLength(10),
            TextInput::make('storage_location')
                ->label('SLOC')
                ->required()
                ->maxLength(10),
            TextInput::make('description')
                ->maxLength(150),
            Select::make('p2p_material_classification_id')
                ->label('Classification')
                ->relationship('materialClassification', 'name')
                ->required(),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('storage_location')->label('SLOC')->searchable()->sortable(),
                TextColumn::make('description')->searchable()->limit(40),
                TextColumn::make('materialClassification.name')->label('Classification')->badge(),
                TextColumn::make('company_code')->label('Company')->placeholder('Global')->toggleable(),
                TextColumn::make('plant')->placeholder('—')->toggleable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('storage_location')
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSlocClassifications::route('/'),
        ];
    }
}
