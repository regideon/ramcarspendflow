<?php

namespace App\Filament\Resources\P2p\Escalations\Tables;

use App\Models\P2p\Escalation;
use App\Services\P2p\P2pEscalationRoutingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EscalationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('batch_number')
                    ->label('Escalation')
                    ->state(fn (Escalation $record): string => $record->requestNumber())
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('reason.name')
                    ->label('Reason')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('goodReceiptState.good_receipt_number')
                    ->label('GR')
                    ->badge()
                    ->searchable(),

                TextColumn::make('goodReceiptState.purchase_order')
                    ->label('PO')
                    ->searchable(),

                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice No')
                    ->placeholder('—'),

                TextColumn::make('invoice.po_numbers')
                    ->label('POs')
                    ->badge()
                    ->listWithLineBreaks()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('current_role')
                    ->label('Current hop')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Escalation::roleLabel($state))
                    ->searchable(),

                TextColumn::make('current_step_order')
                    ->label('Step')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('origin_role')
                    ->label('Raised by')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => Escalation::roleLabel($state)),

                TextColumn::make('escalated_at')
                    ->label('Escalated')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                TextColumn::make('remarks')
                    ->label('Remarks')
                    ->limit(40)
                    ->tooltip(fn (Escalation $record): ?string => $record->remarks)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('escalated_at', 'desc')
            ->filters([
                SelectFilter::make('p2p_escalation_reason_id')
                    ->label('Reason')
                    ->relationship('reason', 'name'),
                SelectFilter::make('current_role')
                    ->label('Current hop')
                    ->options([
                        'procurement' => 'Procurement',
                        'warehouse' => 'Warehouse',
                        'vendor' => 'Vendor',
                        'processor' => 'AP Processor',
                    ]),
            ])
            ->recordActions([
                Action::make('advance')
                    ->label('Advance')
                    ->icon(Heroicon::OutlinedArrowRightCircle)
                    ->color('warning')
                    ->visible(fn (Escalation $record): bool => $record->current_role !== 'origin')
                    ->schema([
                        Textarea::make('remarks')->required()->columnSpanFull(),
                    ])
                    ->action(function (Escalation $record, array $data): void {
                        app(P2pEscalationRoutingService::class)->advance($record, auth()->user(), $data['remarks']);

                        Notification::make()->title('Escalation advanced')->success()->send();
                    }),

                Action::make('resolve')
                    ->label('Resolve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->schema([
                        Textarea::make('remarks')->required()->columnSpanFull(),
                    ])
                    ->action(function (Escalation $record, array $data): void {
                        app(P2pEscalationRoutingService::class)->resolve($record, auth()->user(), $data['remarks']);

                        Notification::make()->title('Escalation resolved')->success()->send();
                    }),
            ])
            ->striped();
    }
}
