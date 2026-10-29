<?php

namespace App\Filament\Resources\P2p\SapLogs\Tables;

use App\Models\P2p\SapApiLog;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SapLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === SapApiLog::TYPE_ODATA ? 'OData (read)' : 'SOAP (write)')
                    ->color(fn (string $state): string => $state === SapApiLog::TYPE_ODATA ? 'info' : 'gray'),

                TextColumn::make('invoice.grc_number')
                    ->label('GRC No.')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('endpoint')
                    ->searchable()
                    ->wrap()
                    ->limit(60),

                TextColumn::make('http_status')
                    ->label('Status')
                    ->badge()
                    ->placeholder('—')
                    ->color(fn ($state): string => match (true) {
                        $state === null => 'gray',
                        (int) $state >= 200 && (int) $state < 300 => 'success',
                        default => 'danger',
                    }),

                TextColumn::make('execution_time')
                    ->label('Exec')
                    ->formatStateUsing(fn ($state): string => $state !== null
                        ? number_format((float) $state * 1000).' ms'
                        : '—')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('By')
                    ->placeholder('System')
                    ->toggleable(),

                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Logged at')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        SapApiLog::TYPE_SOAP => 'SOAP (write)',
                        SapApiLog::TYPE_ODATA => 'OData (read)',
                    ]),

                SelectFilter::make('result')
                    ->label('Result')
                    ->options([
                        'success' => 'Success (2xx)',
                        'failed' => 'Failed',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'success' => $query->whereBetween('http_status', [200, 299]),
                            'failed' => $query->where(function (Builder $query): void {
                                $query->whereNull('http_status')
                                    ->orWhereNotBetween('http_status', [200, 299])
                                    ->orWhereNotNull('error_message');
                            }),
                            default => $query,
                        };
                    }),

                Filter::make('failed')
                    ->label('Only failures')
                    ->query(fn (Builder $query): Builder => $query->failed())
                    ->toggle(),

                Filter::make('created_at')
                    ->label('Logged at')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (SapApiLog $record): string => 'SAP log: '.$record->endpoint)
                    ->modalWidth(Width::FourExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (SapApiLog $record) => view('filament.p2p.sap-log-payload', ['log' => $record])),
            ])
            ->emptyStateHeading('No SAP communication yet')
            ->emptyStateDescription('OData reads and SOAP writes are recorded here once SAP_ENABLED=true.')
            ->striped();
    }
}
