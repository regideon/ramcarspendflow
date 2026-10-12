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
 * The AP processor's worklist: submissions waiting on them. Rows open the bulk
 * accounts-payable screen where the tax/WHT are assigned and the invoice is
 * submitted, escalated or returned.
 *
 * The AP checker's queue is intentionally out of scope (blocked on B1).
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

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return ApprovalsTable::configure($table);
    }

    /**
     * The processor's queue: only submissions awaiting AP, scoped the same way the
     * Invoices list is.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->actionableByApProcessor()
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
