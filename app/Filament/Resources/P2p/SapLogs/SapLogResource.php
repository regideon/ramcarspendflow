<?php

namespace App\Filament\Resources\P2p\SapLogs;

use App\Enums\RoleName;
use App\Filament\Resources\P2p\SapLogs\Pages\ListSapLogs;
use App\Filament\Resources\P2p\SapLogs\Tables\SapLogsTable;
use App\Models\P2p\SapApiLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Admin-only audit trail of SAP communication (OData reads and SOAP writes).
 */
class SapLogResource extends Resource
{
    protected static ?string $model = SapApiLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'P2P';

    protected static ?string $navigationLabel = 'SAP Logs';

    protected static ?string $modelLabel = 'SAP log';

    protected static ?string $pluralModelLabel = 'SAP logs';

    protected static ?string $slug = 'p2p/sap-logs';

    protected static ?int $navigationSort = 90;

    public static function table(Table $table): Table
    {
        return SapLogsTable::configure($table);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(RoleName::Admin) ?? false;
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
            'index' => ListSapLogs::route('/'),
        ];
    }
}
