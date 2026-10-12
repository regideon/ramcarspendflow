<?php

namespace App\Filament\Resources\P2p\Approvals\Tables;

use App\Enums\P2pStatus;
use App\Filament\Pages\P2p\BulkAccountsPayable;
use App\Models\P2p\Invoice;
use App\Services\P2p\P2pThreeWayMatchService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The AP processor queue. The columns mirror the Invoices list so the two read
 * alike; the row opens the bulk accounts-payable processing screen.
 */
class ApprovalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['vendor', 'company']))
            ->columns([
                TextColumn::make('grc_number')
                    ->label('GRC No.')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable()
                    ->limit(30)
                    ->description(fn (Invoice $record): ?string => $record->vendor?->code),

                TextColumn::make('po_numbers')
                    ->label('PO')
                    ->listWithLineBreaks()
                    ->searchable(),

                TextColumn::make('reference_number')
                    ->label('Reference')
                    ->searchable(),

                TextColumn::make('invoice_at')
                    ->label('Invoice Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('gr_numbers')
                    ->label('GR')
                    ->listWithLineBreaks()
                    ->searchable(),

                TextColumn::make('amount_submitted')
                    ->label('Amount')
                    ->numeric(decimalPlaces: 2, thousandsSeparator: ',')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('three_way')
                    ->label('3-Way Match')
                    ->badge()
                    ->state(fn (Invoice $record): string => self::threeWay($record)['label'])
                    ->color(fn (Invoice $record): string => self::threeWay($record)['color'])
                    ->tooltip(fn (Invoice $record): string => self::threeWay($record)['reason']),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('submitted_at')
                    ->label('Waiting')
                    ->sortable()
                    ->description(fn (Invoice $record): ?string => $record->submitted_at?->diffForHumans()),
            ])
            // Oldest first: the processor works the queue in order.
            ->defaultSort('submitted_at')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        P2pStatus::SubmittedToAp->value => P2pStatus::SubmittedToAp->getLabel(),
                        P2pStatus::ApReview->value => P2pStatus::ApReview->getLabel(),
                        P2pStatus::ReturnedToApProcessor->value => P2pStatus::ReturnedToApProcessor->getLabel(),
                    ])
                    ->multiple(),
            ])
            ->recordActions([
                Action::make('process')
                    ->label('Process')
                    ->url(fn (Invoice $record): string => BulkAccountsPayable::getUrl(['invoice' => $record->id])),
            ])
            ->recordUrl(fn (Invoice $record): string => BulkAccountsPayable::getUrl(['invoice' => $record->id]))
            ->searchPlaceholder('Search GRC no., invoice no. or vendor')
            ->emptyStateHeading('Nothing to approve')
            ->emptyStateDescription('Submissions waiting on AP will appear here.')
            ->striped();
    }

    /**
     * The 3-way breakdown for a row, memoised so label, colour and tooltip share
     * one computation per invoice.
     *
     * @return array{label: string, color: string, reason: string}
     */
    private static function threeWay(Invoice $record): array
    {
        /** @var array<int, array{label: string, color: string, reason: string}> $cache */
        static $cache = [];

        return $cache[$record->getKey()] ??= app(P2pThreeWayMatchService::class)->breakdownForInvoice($record);
    }
}
