<?php

namespace App\Filament\Clusters\P2pSettings\Resources\DeliveryTypes;

use App\Filament\Clusters\P2pSettings\P2pSettingsCluster;
use App\Filament\Clusters\P2pSettings\Resources\DeliveryTypes\Pages\ManageDeliveryTypes;
use App\Filament\Concerns\RestrictedToAdmins;
use App\Models\P2p\DeliveryType;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeliveryTypeResource extends Resource
{
    use RestrictedToAdmins;

    protected static ?string $model = DeliveryType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $cluster = P2pSettingsCluster::class;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->maxLength(10)->unique(ignoreRecord: true),
            TextInput::make('name')->required()->maxLength(100),
            TextInput::make('sort')->numeric()->default(0),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('sort')
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDeliveryTypes::route('/'),
        ];
    }
}
