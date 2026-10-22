<?php

namespace App\Filament\Resources\P2p\Invoices\Schemas;

use App\Models\P2p\Invoice;
use App\Models\TaxCode;
use App\Models\WithholdingTaxCode;
use App\Services\P2p\P2pInvoicePanelEditor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The editable half of the P2P request pane. It deliberately contains only the
 * fields the portal owns; lines and attachments keep their dedicated screens.
 */
class InvoiceRecordPanel
{
    public static function edit(Schema $schema, Invoice $invoice): Schema
    {
        $user = auth()->user();
        $editor = app(P2pInvoicePanelEditor::class);

        $canEditVendor = $editor->canEditVendorFields($invoice, $user);
        $canEditProcessor = $editor->canEditProcessorFields($invoice, $user);

        return $schema
            ->statePath('recordData')
            ->components([
                Section::make('Request details')
                    ->description('Fields the requestor can correct before AP processes the request.')
                    ->visible($canEditVendor)
                    ->schema([
                        Grid::make(2)->schema([
                            DatePicker::make('invoice_at')
                                ->label('Invoice date')
                                ->required(),

                            TextInput::make('reference_number')
                                ->label('Reference no(s)')
                                ->helperText('Separate multiple references with commas.')
                                ->maxLength(255),
                        ]),
                    ]),

                Section::make('Tax')
                    ->description('Applied by AP before the request goes to the supervisor.')
                    ->visible($canEditProcessor)
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('tax_code')
                                ->label('Tax code')
                                ->options(fn (): array => TaxCode::query()
                                    ->orderBy('code')
                                    ->pluck('description', 'code')
                                    ->map(fn (string $description, string $code): string => "{$code} - {$description}")
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->required(),

                            Select::make('withholding_tax_code')
                                ->label('Withholding tax code')
                                ->options(fn (): array => WithholdingTaxCode::query()
                                    ->orderBy('code')
                                    ->pluck('description', 'code')
                                    ->map(fn (string $description, string $code): string => "{$code} - {$description}")
                                    ->all())
                                ->searchable()
                                ->preload(),

                            TextInput::make('withholding_tax_base')
                                ->label('Withholding tax base')
                                ->numeric()
                                ->prefix('₱'),

                            Textarea::make('remarks_ap')
                                ->label('AP notes')
                                ->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }
}
