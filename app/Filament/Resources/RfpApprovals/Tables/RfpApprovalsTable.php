<?php

namespace App\Filament\Resources\RfpApprovals\Tables;

use App\Enums\RfpRequestType;
use App\Enums\RfpStatus;
use App\Filament\Actions\OpenCommentsAction;
use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Filament\Resources\Rfps\Tables\RfpsTable;
use App\Models\Rfp\Item;
use App\Models\Rfp\Rfp;
use App\Models\Vendor;
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
                ->with(['company', 'dueType', 'vendor', 'requestor', 'currentApprover', 'items.currency', 'items.vendor', 'items.oneTimeVendor'])
                ->withCount('items')
                ->withUnreadCommentsCountFor(auth()->user()))
            ->columns([
                TextColumn::make('rfp_number')
                    ->label('RFP ID')
                    // The search bar also finds RFPs by the invoice number of any line
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('rfp_number', 'like', "%{$search}%")
                        ->orWhereHas('items', fn (Builder $query) => $query->where('invoice_number', 'like', "%{$search}%")))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('id', $direction))
                    ->weight('medium'),
                RfpsTable::dueDateColumn(),
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
                // Who was actually paid on the lines (vendors / one-time vendors on Reimbursement items)
                TextColumn::make('vendors')
                    ->label('Vendors')
                    ->state(fn (Rfp $record): array => static::itemVendorNames($record))
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->placeholder('—')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->orWhereHas('items', fn (Builder $items) => $items
                        ->whereHas('vendor', fn (Builder $vendor) => $vendor->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('oneTimeVendor', fn (Builder $vendor) => $vendor->where('name', 'like', "%{$search}%")->orWhere('tin', 'like', "%{$search}%"))))
                    ->toggleable(),
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
            ->searchPlaceholder('Search RFP ID, invoice no., payee, vendor or requestor')
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->relationship('company', 'name'),
                // The RFP's payee, or a vendor on any of its items
                SelectFilter::make('vendor')
                    ->label('Vendor')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Vendor::query()
                        ->where(fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orderBy('name')
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (Vendor $vendor): array => [$vendor->id => "{$vendor->code} · {$vendor->name}"])
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => ($vendor = Vendor::find($value)) ? "{$vendor->code} · {$vendor->name}" : null)
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $query, $vendorId) => $query->where(fn (Builder $query) => $query
                        ->where('vendor_id', $vendorId)
                        ->orWhereHas('items', fn (Builder $items) => $items->where('vendor_id', $vendorId))))),
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

    /**
     * Distinct vendors paid on the RFP's items.
     *
     * @return list<string>
     */
    public static function itemVendorNames(Rfp $record): array
    {
        return $record->items
            ->map(fn (Item $item): ?string => $item->paidToName())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
