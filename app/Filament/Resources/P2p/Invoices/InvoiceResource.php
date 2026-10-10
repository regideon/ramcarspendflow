<?php

namespace App\Filament\Resources\P2p\Invoices;

use App\Enums\RoleName;
use App\Filament\Resources\P2p\Invoices\Pages\ListInvoices;
use App\Filament\Resources\P2p\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\P2p\Invoices\Tables\InvoicesTable;
use App\Models\P2p\Invoice;
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
 * The submission worklist: "Invoices" for the vendor, "Accounts Payables" for staff.
 * One submission can cover several POs/GRs.
 */
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'P2P';

    protected static ?string $modelLabel = 'invoice';

    protected static ?string $pluralModelLabel = 'invoices';

    protected static ?string $slug = 'p2p/invoices';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'grc_number';

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->hasRole(RoleName::Vendor) ? 'Invoices' : 'Accounts Payables';
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Invoice')->schema([
                TextEntry::make('grc_number')->label('GRC no.'),
                TextEntry::make('status')->badge(),
                TextEntry::make('invoice_number')->label('Invoice no.')->placeholder('—'),
                TextEntry::make('vendor.name')->label('Vendor'),
                TextEntry::make('company.short_name')->label('Company'),
                TextEntry::make('invoice_at')->label('Invoice date')->dateTime()->placeholder('—'),
                TextEntry::make('amount_submitted')->label('Amount')->money('PHP'),
                TextEntry::make('vat')->money('PHP'),
                TextEntry::make('ewt')->label('WHT')->money('PHP'),
                TextEntry::make('net')->label('Net')->money('PHP'),
                TextEntry::make('submitted_at')->label('Submitted')->dateTime()->placeholder('—'),
                TextEntry::make('po_numbers')->label('POs')->listWithLineBreaks()->placeholder('—'),
                TextEntry::make('gr_numbers')->label('GRs')->listWithLineBreaks()->placeholder('—'),
            ])->columns(3),
        ]);
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
            'view' => ViewInvoice::route('/{record}'),
        ];
    }
}
