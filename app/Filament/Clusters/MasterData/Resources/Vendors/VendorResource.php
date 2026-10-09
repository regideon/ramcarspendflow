<?php

namespace App\Filament\Clusters\MasterData\Resources\Vendors;

use App\Filament\Clusters\MasterData\MasterDataCluster;
use App\Filament\Clusters\MasterData\Resources\Vendors\Pages\CreateVendor;
use App\Filament\Clusters\MasterData\Resources\Vendors\Pages\EditVendor;
use App\Filament\Clusters\MasterData\Resources\Vendors\Pages\ListVendors;
use App\Filament\Clusters\MasterData\Resources\Vendors\RelationManagers\CompaniesRelationManager;
use App\Filament\Clusters\MasterData\Resources\Vendors\Schemas\VendorForm;
use App\Filament\Clusters\MasterData\Resources\Vendors\Tables\VendorsTable;
use App\Filament\Concerns\RestrictedToAdmins;
use App\Models\Vendor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VendorResource extends Resource
{
    use RestrictedToAdmins;

    protected static ?string $model = Vendor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $cluster = MasterDataCluster::class;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return VendorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VendorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CompaniesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendors::route('/'),
            'create' => CreateVendor::route('/create'),
            'edit' => EditVendor::route('/{record}/edit'),
        ];
    }
}
