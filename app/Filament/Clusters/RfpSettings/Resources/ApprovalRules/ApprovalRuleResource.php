<?php

namespace App\Filament\Clusters\RfpSettings\Resources\ApprovalRules;

use App\Filament\Clusters\RfpSettings\Resources\ApprovalRules\Pages\CreateApprovalRule;
use App\Filament\Clusters\RfpSettings\Resources\ApprovalRules\Pages\EditApprovalRule;
use App\Filament\Clusters\RfpSettings\Resources\ApprovalRules\Pages\ListApprovalRules;
use App\Filament\Clusters\RfpSettings\Resources\ApprovalRules\Schemas\ApprovalRuleForm;
use App\Filament\Clusters\RfpSettings\Resources\ApprovalRules\Tables\ApprovalRulesTable;
use App\Filament\Clusters\RfpSettings\RfpSettingsCluster;
use App\Filament\Concerns\RestrictedToAdmins;
use App\Models\Rfp\ApprovalRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ApprovalRuleResource extends Resource
{
    use RestrictedToAdmins;

    protected static ?string $model = ApprovalRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $cluster = RfpSettingsCluster::class;

    protected static ?string $navigationLabel = 'Approval Matrix';

    protected static ?string $modelLabel = 'approval tier';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return ApprovalRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApprovalRulesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApprovalRules::route('/'),
            'create' => CreateApprovalRule::route('/create'),
            'edit' => EditApprovalRule::route('/{record}/edit'),
        ];
    }
}
