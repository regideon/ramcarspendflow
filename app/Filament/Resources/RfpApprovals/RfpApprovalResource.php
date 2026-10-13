<?php

namespace App\Filament\Resources\RfpApprovals;

use App\Enums\RoleName;
use App\Filament\Resources\RfpApprovals\Pages\ListRfpApprovals;
use App\Filament\Resources\RfpApprovals\Pages\ViewRfpApproval;
use App\Filament\Resources\RfpApprovals\Tables\RfpApprovalsTable;
use App\Filament\Resources\Rfps\Schemas\RfpInfolist;
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
 * RFP Approvals (legacy "Approvals"): the queue of submitted RFPs for business approvers
 * and Accounting. Separate from My Requests, and from the future P2P Approvals.
 */
class RfpApprovalResource extends Resource
{
    protected static ?string $model = Rfp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'RFP';

    protected static ?string $navigationLabel = 'RFP Approvals';

    protected static ?string $modelLabel = 'RFP approval';

    protected static ?string $pluralModelLabel = 'RFP approvals';

    protected static ?string $slug = 'rfp-approvals';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'rfp_number';

    public static function infolist(Schema $schema): Schema
    {
        return RfpInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RfpApprovalsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->inApprovalsOf(auth()->user());
    }

    /**
     * RFPs waiting for the signed-in user.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = Rfp::query()->awaiting(auth()->user())->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Waiting for your action';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(RoleName::rfpApprovalsUsers()) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRfpApprovals::route('/'),
            'view' => ViewRfpApproval::route('/{record}'),
        ];
    }
}
