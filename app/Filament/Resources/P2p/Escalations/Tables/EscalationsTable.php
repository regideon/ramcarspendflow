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
use Illuminate\Contracts\View\View;

/**
 * Escalations a team must act on: the current hop reads from the reason's route,
 * so the row action is the hop's own custom action when it defines one (e.g.
 * "Reverse & re-GR"), otherwise Advance / Resolve.
 */
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
                self::hopAction(),

                Action::make('resolve')
                    ->label('Resolve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Escalation $record): bool => $record->currentStepAction() === null)
                    ->schema([
                        Textarea::make('remarks')->required()->columnSpanFull(),
                    ])
                    ->action(function (Escalation $record, array $data): void {
                        app(P2pEscalationRoutingService::class)->resolve($record, auth()->user(), $data['remarks']);

                        Notification::make()->title('Escalation resolved')->success()->send();
                    }),

                Action::make('history')
                    ->label('History')
                    ->icon(Heroicon::OutlinedClock)
                    ->color('gray')
                    ->modalHeading('Approval history')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (Escalation $record): View => view('filament.components.approval-history-timeline', [
                        'record' => $record->invoice,
                    ])),
            ])
            ->striped();
    }

    /**
     * The hop's action. A step with a custom action (e.g. "Reverse & re-GR") supplies
     * the label and lets the route settle the escalation; otherwise the generic
     * "Advance" walks the route one hop at a time.
     */
    protected static function hopAction(): Action
    {
        return Action::make('advance')
            ->label(fn (Escalation $record): string => $record->currentStepAction() !== null
                ? ($record->currentActionLabel() ?? $record->currentStepAction()->label ?? 'Advance')
                : 'Advance')
            ->icon(fn (Escalation $record): string|\BackedEnum => $record->currentStepAction() !== null
                ? Heroicon::OutlinedArrowUturnLeft
                : Heroicon::OutlinedArrowRightCircle)
            ->color(fn (Escalation $record): string => $record->currentStepAction() !== null ? 'danger' : 'warning')
            ->visible(fn (Escalation $record): bool => $record->current_role !== 'origin')
            ->modalHeading(fn (Escalation $record): string => $record->currentActionLabel() ?? 'Advance escalation')
            ->schema([
                Textarea::make('remarks')
                    ->required()
                    ->columnSpanFull(),
            ])
            ->action(function (Escalation $record, array $data): void {
                app(P2pEscalationRoutingService::class)->advance($record, auth()->user(), $data['remarks']);

                Notification::make()->title('Escalation advanced')->success()->send();
            });
    }
}
