<?php

namespace App\Filament\Resources\P2p\Invoices;

use App\Enums\RoleName;
use App\Filament\Resources\P2p\Invoices\Pages\ListInvoices;
use App\Filament\Resources\P2p\Invoices\Tables\InvoicesTable;
use App\Models\P2p\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The submission worklist: "Invoices" for the vendor, "Accounts Payables" for staff.
 * One submission can cover several POs/GRs.
 */
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = '-';

    protected static ?string $slug = 'p2p/invoices';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'grc_number';

    public static function getNavigationLabel(): string
    {
        return 'P2P requests';
    }

    public static function getModelLabel(): string
    {
        return 'P2P requests';
    }

    public static function getPluralModelLabel(): string
    {
        return 'P2P Requests';
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([RoleName::Vendor, RoleName::ApProcessor, RoleName::Admin]) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
        ];
    }
}
