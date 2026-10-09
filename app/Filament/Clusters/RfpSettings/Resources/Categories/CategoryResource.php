<?php

namespace App\Filament\Clusters\RfpSettings\Resources\Categories;

use App\Filament\Clusters\RfpSettings\Resources\Categories\Pages\CreateCategory;
use App\Filament\Clusters\RfpSettings\Resources\Categories\Pages\EditCategory;
use App\Filament\Clusters\RfpSettings\Resources\Categories\Pages\ListCategories;
use App\Filament\Clusters\RfpSettings\Resources\Categories\RelationManagers\SubcategoriesRelationManager;
use App\Filament\Clusters\RfpSettings\Resources\Categories\Schemas\CategoryForm;
use App\Filament\Clusters\RfpSettings\Resources\Categories\Tables\CategoriesTable;
use App\Filament\Clusters\RfpSettings\RfpSettingsCluster;
use App\Filament\Concerns\RestrictedToAdmins;
use App\Models\Rfp\Category;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    use RestrictedToAdmins;

    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static ?string $cluster = RfpSettingsCluster::class;

    protected static ?string $navigationLabel = 'Expense Categories';

    protected static ?string $modelLabel = 'expense category';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SubcategoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
