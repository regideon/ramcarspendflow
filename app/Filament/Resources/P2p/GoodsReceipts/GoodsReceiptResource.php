<?php

namespace App\Filament\Resources\P2p\GoodsReceipts;

use App\Enums\RoleName;
use App\Filament\Resources\P2p\GoodsReceipts\Pages\ListGoodsReceipts;
use App\Filament\Resources\P2p\GoodsReceipts\Pages\ViewGoodsReceipt;
use App\Filament\Resources\P2p\GoodsReceipts\Tables\GoodsReceiptsTable;
use App\Models\P2p\GoodReceiptState;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Goods receipts the vendor can process. Navigation is hidden — the list is reached
 * from Purchase Orders ("View GR's") or the Invoices "Process" link. Processing
 * several GRs (across POs) at once is the normal path.
 */
class GoodsReceiptResource extends Resource
{
    protected static ?string $model = GoodReceiptState::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'P2P';

    protected static ?string $navigationLabel = 'Goods Receipts';

    protected static ?string $modelLabel = 'goods receipt';

    protected static ?string $pluralModelLabel = 'goods receipts';

    protected static ?string $slug = 'p2p/goods-receipts';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'good_receipt_number';

    public static function table(Table $table): Table
    {
        return GoodsReceiptsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Goods receipt')->schema([
                TextEntry::make('good_receipt_number')->label('GR no.'),
                TextEntry::make('purchase_order')->label('PO no.'),
                TextEntry::make('status')->badge(),
                TextEntry::make('vendor.name')->label('Vendor'),
                TextEntry::make('company.short_name')->label('Company'),
                TextEntry::make('quantity_total')->label('Qty'),
                TextEntry::make('amount_total')->money('PHP'),
                TextEntry::make('reversed_at')->label('Reversed')->dateTime()->placeholder('—'),
                TextEntry::make('replacement_gr_number')->label('Replacement GR')->placeholder('—'),
            ])->columns(3),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user !== null && $user->hasRole(RoleName::Vendor) && ! $user->hasRole(RoleName::Admin)) {
            $query->where('vendor_id', $user->vendor_id);
        }

        // The Purchase Orders -> GRs deep-link scope (gr_numbers / purchase_orders) is
        // applied by ListGoodsReceipts so it survives Livewire interactions and keeps
        // the base-number "-P" fallback. See ListGoodsReceipts::applyGrOrPoFilter().

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

    public static function getPages(): array
    {
        return [
            'index' => ListGoodsReceipts::route('/'),
            'view' => ViewGoodsReceipt::route('/{record}'),
        ];
    }
}
