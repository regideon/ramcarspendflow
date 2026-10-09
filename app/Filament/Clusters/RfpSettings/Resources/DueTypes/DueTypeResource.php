<?php

namespace App\Filament\Clusters\RfpSettings\Resources\DueTypes;

use App\Filament\Clusters\RfpSettings\Resources\DueTypes\Pages\CreateDueType;
use App\Filament\Clusters\RfpSettings\Resources\DueTypes\Pages\EditDueType;
use App\Filament\Clusters\RfpSettings\Resources\DueTypes\Pages\ListDueTypes;
use App\Filament\Clusters\RfpSettings\Resources\DueTypes\Schemas\DueTypeForm;
use App\Filament\Clusters\RfpSettings\Resources\DueTypes\Tables\DueTypesTable;
use App\Filament\Clusters\RfpSettings\RfpSettingsCluster;
use App\Filament\Concerns\RestrictedToAdmins;
use App\Models\Rfp\DueType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DueTypeResource extends Resource
{
    use RestrictedToAdmins;

    protected static ?string $model = DueType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $cluster = RfpSettingsCluster::class;

    protected static ?string $navigationLabel = 'Due Types';

    protected static ?string $modelLabel = 'due type';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DueTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DueTypesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDueTypes::route('/'),
            'create' => CreateDueType::route('/create'),
            'edit' => EditDueType::route('/{record}/edit'),
        ];
    }
}
