<?php

namespace App\Filament\Resources\RfpApprovals\Tables;

use App\Enums\RfpRequestType;
use App\Enums\RfpStatus;
use App\Filament\Actions\OpenCommentsAction;
use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Models\Rfp\Rfp;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Columns of the legacy Approvals list.
 */
class RfpApprovalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['company', 'dueType', 'vendor', 'requestor', 'currentApprover', 'items.currency'])
                ->withCount('items'))
            ->columns([
                TextColumn::make('rfp_number')
                    ->label('RFP ID')
                    // The search bar also finds RFPs by the invoice number of any line
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('rfp_number', 'like', "%{$search}%")
                        ->orWhereHas('items', fn (Builder $query) => $query->where('invoice_number', 'like', "%{$search}%")))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('id', $direction))
                    ->weight('medium'),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('dueType.name')
                    ->label('Urgency')
                    ->badge()
                    ->color(fn (Rfp $record): string => $record->dueType?->code === 'urgent' ? 'danger' : 'gray'),
                TextColumn::make('company.short_name')
                    ->label('Company'),
                TextColumn::make('vendor.name')
                    ->label('Payee')
                    ->searchable()
                    ->limit(35)
                    ->tooltip(fn (Rfp $record): ?string => $record->vendor?->name),
                TextColumn::make('items_count')
                    ->label('Total Quantity')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('total_php_amount')
                    ->label('Total Amount')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('PHP ')
                    ->description(fn (Rfp $record): ?string => collect($record->foreignTotals())
                        ->map(fn (string $amount, string $code): string => $code.' '.number_format((float) $amount, 2))
                        ->implode(' · ') ?: null)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('requestor.name')
                    ->label('Requestor')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('current_approver')
                    ->label('Current Approver')
                    ->state(fn (Rfp $record): ?string => $record->currentApproverLabel())
                    ->badge()
                    ->color('danger')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Created Date')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Last Update')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder('Search RFP ID, invoice no., payee or requestor')
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->relationship('company', 'name'),
                SelectFilter::make('rfp_due_type_id')
                    ->label('Urgency')
                    ->relationship('dueType', 'name'),
                SelectFilter::make('request_type')
                    ->label('RFP type')
                    ->options(RfpRequestType::class),
                SelectFilter::make('status')
                    ->options(RfpStatus::class)
                    ->multiple(),
                Filter::make('created_at')
                    ->label('Date/s')
                    ->schema([
                        DatePicker::make('created_from')->label('Start date'),
                        DatePicker::make('created_until')->label('End date'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['created_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['created_until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                OpenCommentsAction::make(),
                ViewAction::make()->iconButton(),
            ])
            ->recordUrl(fn (Rfp $record): string => RfpApprovalResource::getUrl('view', ['record' => $record]))
            ->striped();
    }
}
