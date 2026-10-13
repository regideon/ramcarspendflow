<?php

namespace App\Filament\Resources\P2p\Escalations;

use App\Enums\RoleName;
use App\Filament\Resources\P2p\Escalations\Pages\ListEscalations;
use App\Filament\Resources\P2p\Escalations\Tables\EscalationsTable;
use App\Models\P2p\Escalation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Escalations raised against goods receipts, shown to the teams that handle the
 * hops (Procurement, Warehouse). Each row is one GR; rows created together share
 * a batch number and are shown as one request.
 */
class EscalationResource extends Resource
{
    protected static ?string $model = Escalation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static string|UnitEnum|null $navigationGroup = 'P2P';

    protected static ?string $navigationLabel = 'P2P Escalations';

    protected static ?string $modelLabel = 'escalation';

    protected static ?string $pluralModelLabel = 'escalations';

    protected static ?string $slug = 'p2p/escalations';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'escalation_number';

    public static function table(Table $table): Table
    {
        return EscalationsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', Escalation::STATUS_OPEN)
            // `reason.steps` feeds each row's current-hop action/label, so eager-load
            // it to avoid a query per row.
            ->with(['reason.steps', 'goodReceiptState', 'invoice.vendor', 'invoice.company']);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([
            RoleName::Admin,
            RoleName::Procurement,
            RoleName::Warehouse,
            RoleName::WarehouseRawMaterials,
            RoleName::WarehouseSpareParts,
        ]) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEscalations::route('/'),
        ];
    }
}
