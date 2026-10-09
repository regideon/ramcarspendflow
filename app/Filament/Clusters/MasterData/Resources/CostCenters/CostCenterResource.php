<?php

namespace App\Filament\Clusters\MasterData\Resources\CostCenters;

use App\Filament\Clusters\MasterData\MasterDataCluster;
use App\Filament\Clusters\MasterData\Resources\CostCenters\Pages\CreateCostCenter;
use App\Filament\Clusters\MasterData\Resources\CostCenters\Pages\EditCostCenter;
use App\Filament\Clusters\MasterData\Resources\CostCenters\Pages\ListCostCenters;
use App\Filament\Clusters\MasterData\Resources\CostCenters\RelationManagers\GlAccountsRelationManager;
use App\Filament\Clusters\MasterData\Resources\CostCenters\Schemas\CostCenterForm;
use App\Filament\Clusters\MasterData\Resources\CostCenters\Tables\CostCentersTable;
use App\Filament\Concerns\RestrictedToAdmins;
use App\Models\CostCenter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CostCenterResource extends Resource
{
    use RestrictedToAdmins;

    protected static ?string $model = CostCenter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $cluster = MasterDataCluster::class;

    protected static ?int $navigationSort = 9;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return CostCenterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CostCentersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            GlAccountsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCostCenters::route('/'),
            'create' => CreateCostCenter::route('/create'),
            'edit' => EditCostCenter::route('/{record}/edit'),
        ];
    }
}
