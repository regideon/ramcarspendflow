<?php

namespace App\Filament\Resources\Rfps;

use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Resources\Rfps\Pages\CreateRfp;
use App\Filament\Resources\Rfps\Pages\EditRfp;
use App\Filament\Resources\Rfps\Pages\ListRfps;
use App\Filament\Resources\Rfps\Pages\ViewRfp;
use App\Filament\Resources\Rfps\Schemas\RfpForm;
use App\Filament\Resources\Rfps\Schemas\RfpInfolist;
use App\Filament\Resources\Rfps\Tables\RfpsTable;
use App\Models\Rfp\Rfp;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The requestor's own Requests for Payment (legacy "My RFPs"). Approvers and Accounting
 * work them in RFP Approvals.
 */
class RfpResource extends Resource
{
    protected static ?string $model = Rfp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'RFP';

    protected static ?string $navigationLabel = 'RFP Requests'; // My Requests

    protected static ?string $modelLabel = 'request for payment';

    protected static ?string $pluralModelLabel = 'requests for payment';

    protected static ?string $slug = 'rfps';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'rfp_number';

    public static function form(Schema $schema): Schema
    {
        return RfpForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RfpInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RfpsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->ownedBy(auth()->user());
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([RoleName::Requestor, RoleName::Admin]) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole([RoleName::Requestor, RoleName::Admin]) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return $record->requestor_id === auth()->id() && $record->status->isEditableByRequestor();
    }

    public static function canDelete(Model $record): bool
    {
        return $record->requestor_id === auth()->id() && $record->status === RfpStatus::Draft;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRfps::route('/'),
            'create' => CreateRfp::route('/create'),
            'view' => ViewRfp::route('/{record}'),
            'edit' => EditRfp::route('/{record}/edit'),
        ];
    }
}
