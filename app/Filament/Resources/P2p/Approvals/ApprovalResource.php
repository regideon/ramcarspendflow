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
 * The list is the whole set of submissions the viewer may see; the tabs on
 * {@see ListApprovals} narrow it (their own queue, the other queues, escalated,
 * posted). Only the "For my action" tab is actionable — the rest are read-only.
 */
class ApprovalResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = '-';

    protected static ?string $navigationLabel = 'P2P Approvals';

    protected static ?string $modelLabel = 'P2P approval';

    protected static ?string $pluralModelLabel = 'P2P approvals';

    protected static ?string $slug = 'p2p/approvals';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'grc_number';

    public static function table(Table $table): Table
    {
        return ApprovalsTable::configure($table);
    }

    /**
     * Every submission the viewer may see. The tabs narrow this: "For my action"
     * is the viewer's own queue, the others are read-only overviews.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->visibleTo(auth()->user())
            ->with(['vendor', 'company']);
    }

    /**
     * The badge is the viewer's own actionable queue, not everything they can see.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = parent::getEloquentQuery()->actionableBy(auth()->user())->count();

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
