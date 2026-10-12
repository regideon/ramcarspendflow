<?php

namespace App\Filament\Exports;

use App\Enums\RoleName;
use App\Models\P2p\Invoice;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

/**
 * Exports the Invoices / Accounts Payables list. The vendor never receives the
 * amount column, matching the list's visibility rules.
 */
class P2pInvoiceExporter extends Exporter
{
    protected static ?string $model = Invoice::class;

    /**
     * @return array<int, ExportColumn>
     */
    public static function getColumns(): array
    {
        $hideAmount = auth()->user()?->hasRole(RoleName::Vendor) ?? false;

        return array_values(array_filter([
            ExportColumn::make('grc_number')
                ->label('GRC No.'),

            $hideAmount
                ? null
                : ExportColumn::make('vendor.name')
                    ->label('Vendor'),

            $hideAmount
                ? ExportColumn::make('company.name')->label('Company')
                : null,

            ExportColumn::make('po_numbers')
                ->label('PO'),

            ExportColumn::make('reference_number')
                ->label('Reference Number'),

            ExportColumn::make('invoice_at')
                ->label('Invoice Date'),

            ExportColumn::make('gr_numbers')
                ->label('GR'),

            $hideAmount
                ? null
                : ExportColumn::make('amount_submitted')
                    ->label('Amount'),

            ExportColumn::make('invoice_number')
                ->label('Invoice No'),

            ExportColumn::make('status')
                ->label('Status'),
        ]));
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your invoice export has completed and '.Str::of('row')->counted($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Str::of('row')->counted($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
