<?php

namespace App\Filament\Resources\P2p\Approvals;

use App\Enums\RoleName;
use App\Filament\Resources\P2p\Approvals\Pages\ListApprovals;
use App\Filament\Resources\P2p\Approvals\Tables\ApprovalsTable;
use App\Models\P2p\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The AP worklist: submissions waiting on the processor, or — for the AP
 * Supervisor (checker) — those waiting to be approved and parked in SAP.
 *
 * Shown as a single navigation destination rather than a group.
 */
class ApprovalResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'P2P';

    protected static ?string $navigationLabel = 'P2P Approvals';

    protected static ?string $modelLabel = 'approval';

    protected static ?string $pluralModelLabel = 'approvals';

    protected static ?string $slug = 'p2p/approvals';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'grc_number';

    public static function table(Table $table): Table
    {
        return ApprovalsTable::configure($table);
    }

    /**
     * The viewer's queue: the processor sees what they must act on, the supervisor
     * sees what is waiting on them. Both are scoped the way the Invoices list is.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->actionableBy(auth()->user())
            ->visibleTo(auth()->user())
            ->with(['vendor', 'company']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([
            RoleName::ApProcessor,
            RoleName::ApSupervisor,
            RoleName::Admin,
        ]) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApprovals::route('/'),
        ];
    }
}
