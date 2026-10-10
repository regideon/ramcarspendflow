<?php

namespace App\Filament\Resources\P2p\PurchaseOrders;

use App\Enums\RoleName;
use App\Filament\Resources\P2p\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Resources\P2p\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\Resources\P2p\PurchaseOrders\Tables\PurchaseOrdersTable;
use App\Models\P2p\SapPurchaseOrder;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * SAP purchase orders (read-only mirror). Vendors see their own POs and can trigger
 * a "Sync from SAP".
 */
class PurchaseOrderResource extends Resource
{
    protected static ?string $model = SapPurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = 'P2P';

    protected static ?string $navigationLabel = 'Purchase Orders';

    protected static ?string $modelLabel = 'purchase order';

    protected static ?string $pluralModelLabel = 'purchase orders';

    protected static ?string $slug = 'p2p/purchase-orders';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'purchase_order';

    public static function table(Table $table): Table
    {
        return PurchaseOrdersTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Purchase order')->schema([
                TextEntry::make('purchase_order')->label('PO no.'),
                TextEntry::make('purchase_order_type')->label('Type'),
                TextEntry::make('vendor_code')->label('Vendor'),
                TextEntry::make('company_code')->label('Company'),
                TextEntry::make('status_id')->badge(),
                TextEntry::make('currency_key')->label('Currency'),
                TextEntry::make('total_amount')->money(fn (SapPurchaseOrder $record): string => $record->currency_key ?? 'PHP'),
                TextEntry::make('purchase_order_date')->label('PO date')->date(),
                TextEntry::make('changed_on')->label('Last change')->date(),
                TextEntry::make('created_by')->label('Created by'),
            ])->columns(3),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user !== null && $user->hasRole(RoleName::Vendor) && ! $user->hasRole(RoleName::Admin)) {
            $query->where('vendor_code', $user->vendor?->code);
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([RoleName::Vendor, RoleName::Admin]) ?? false;
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
            'index' => ListPurchaseOrders::route('/'),
            'view' => ViewPurchaseOrder::route('/{record}'),
        ];
    }
}
