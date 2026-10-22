<?php

namespace App\Filament\Resources\P2p\Invoices\Tables;

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Actions\OpenCommentsAction;
use App\Filament\Pages\P2p\BulkAccountsPayable;
use App\Filament\Pages\P2p\BulkGoodsReceipt;
use App\Models\P2p\Invoice;
use App\Services\P2p\P2pThreeWayMatchService;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['vendor', 'company'])
                ->withUnreadCommentsCountFor(auth()->user()))
            ->columns([
                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable()
                    ->limit(30)
                    ->description(fn (Invoice $record): ?string => $record->vendor?->code)
                    ->hidden(fn (): bool => auth()->user()?->hasRole(RoleName::Vendor) ?? false),

                TextColumn::make('company.name')
                    ->label('Company')
                    ->hidden(fn (): bool => ! (auth()->user()?->hasRole(RoleName::Vendor) ?? false)),

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
                    ->sortable()
                    ->hidden(fn (): bool => auth()->user()?->hasRole(RoleName::Vendor) ?? false),

                TextColumn::make('invoice_number')
                    ->label('Invoice No')
                    // SAP fills this in only after the invoice is parked/posted.
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('three_way')
                    ->label('3-Way Match')
                    ->badge()
                    ->state(fn (Invoice $record): string => self::threeWay($record)['label'])
                    ->color(fn (Invoice $record): string => self::threeWay($record)['color'])
                    ->tooltip(fn (Invoice $record): string => self::threeWay($record)['reason'])
                    ->toggleable(isToggledHiddenByDefault: true),

                ViewColumn::make('attachments')
                    ->label('Attachments')
                    ->view('filament.tables.columns.attachments-column')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(P2pStatus::class)
                    ->multiple(),
            ])
            ->recordActions([
                OpenCommentsAction::make(),

                Action::make('review')
                    ->label('Review')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->visible(fn (): bool => auth()->user()?->hasAnyRole([
                        RoleName::ApProcessor,
                        RoleName::ApSupervisor,
                        RoleName::Admin,
                    ]) ?? false)
                    ->url(fn (Invoice $record): string => BulkAccountsPayable::getUrl(['invoice' => $record->id])),

                Action::make('resubmit')
                    ->label('Resubmit')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (Invoice $record): bool => $record->status === P2pStatus::ReturnedToVendor
                        && (auth()->user()?->hasRole(RoleName::Vendor) ?? false))
                    ->url(fn (Invoice $record): string => BulkGoodsReceipt::getUrl(['invoice' => $record->id])),
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::Eye)
                    ->color('gray')
                    ->iconButton()
                    ->action(fn (Invoice $record, Component $livewire) => $livewire->selectRecord($record->getKey())),
            ])
            ->recordAction('view')
            ->recordUrl(null)
            ->searchPlaceholder('Search GRC no., invoice no. or vendor')
            ->striped();
    }

    /**
     * The 3-way breakdown for a row, memoised so the label, colour and tooltip
     * resolvers share one computation per invoice.
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
