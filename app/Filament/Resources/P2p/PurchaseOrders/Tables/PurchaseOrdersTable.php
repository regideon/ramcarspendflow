<?php

namespace App\Filament\Resources\P2p\PurchaseOrders\Tables;

use App\Enums\P2pPurchaseOrderStatus;
use App\Enums\RoleName;
use App\Filament\Resources\P2p\GoodsReceipts\GoodsReceiptResource;
use App\Models\Company;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        $pendingPurchaseOrders = GoodReceiptState::query()
            ->where('status', 1)
            ->distinct()
            ->pluck('purchase_order')
            ->flip();

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('goodReceipts', 'company')
                // Only POs that have at least one GR can be processed.
                ->whereNotNull('gr_numbers'))
            ->columns([
                TextColumn::make('purchase_order')
                    ->label('PO Number')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    // Read by the Alpine row-lock to know each row's company and
                    // vendor code; the lock refuses to mix either.
                    ->extraCellAttributes(fn (SapPurchaseOrder $record): array => [
                        'data-company-code' => $record->company_code,
                        'data-vendor-code' => $record->vendor_code,
                    ]),
                TextColumn::make('GR Numbers')
                    ->label('GR Numbers')
                    ->getStateUsing(fn (SapPurchaseOrder $record): array => $record->gr_numbers
                        ? self::cleanGrNumbers($record->gr_numbers)
                        : ['-'] // Return an array with a placeholder so Filament can render it
                    )
                    ->listWithLineBreaks()
                    ->toggleable(),
                TextColumn::make('vendor_code')
                    ->label('Vendor')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: fn (): bool => auth()->user()?->hasRole(RoleName::Vendor) ?? false),
                TextColumn::make('company.name')
                    ->label('Company')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: fn (): bool => ! (auth()->user()?->hasRole(RoleName::Vendor) ?? false)),
                TextColumn::make('currency_key')
                    ->label('Currency')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd()
                    ->sortable()
                    ->visible(fn (): bool => ! (auth()->user()?->hasRole(RoleName::Vendor) ?? false)),
                TextColumn::make('purchase_order_date')
                    ->label('PO date')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status_id')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('quantity_po_balance')
                    ->label('PO balance')
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),
            ])
            ->persistInSession(false)
            ->defaultSort('changed_on', 'desc')
            ->filters([
                SelectFilter::make('company_code')
                    // Suppliers get the union of their assigned companies and
                    // the companies that appear on their POs, so a PO for a
                    // company they have not been registered under is still
                    // selectable. Admins keep every company.
                    ->options(fn (): array => self::companyFilterOptions(auth()->user()))
                    ->label('Company')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('vendor_code')
                    ->label('Vendor Code')
                    ->searchable()
                    ->preload()
                    ->options(fn (): array => self::vendorFilterOptions(auth()->user())),
                SelectFilter::make('status_id')
                    ->label('Status')
                    ->options(P2pPurchaseOrderStatus::class),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            // Apply filter changes immediately (no "Apply filters" button) so the
            // selection-driven company filter takes effect on the next page
            // without an extra click.
            ->deferFilters(false)
            ->hiddenFilterIndicators()
            ->deselectAllRecordsWhenFiltered(false)
            // Completed and Fully Submitted POs are terminal: they have no GRs
            // left to process, so selecting them is a dead click.
            ->checkIfRecordIsSelectableUsing(
                fn (SapPurchaseOrder $record): bool => in_array(
                    $record->status_id,
                    [P2pPurchaseOrderStatus::Pending, P2pPurchaseOrderStatus::PartiallySubmitted],
                    true,
                ),
            )
            // Filament keeps row selection in Alpine until an action mounts, so
            // the server does not re-render on a checkbox click. This binds a
            // small Alpine/DOM helper that (a) instantly hides/disables rows of a
            // different company or vendor and (b) pushes the locked values into
            // the table filters so they survive pagination. The bulk action's
            // server-side guard stays the source of truth.
            ->extraAttributes(['x-init' => 'window.filamentCompanyRowLock?.($el, $wire)'])
            ->recordUrl(fn ($record) => self::goodsReceiptsUrl($record, $pendingPurchaseOrders))
            ->recordActions([
                Action::make('viewGrs')
                    ->label("View GR's")
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->url(fn (SapPurchaseOrder $record): string => GoodsReceiptResource::getUrl('index', [
                        'purchase_orders' => [$record->purchase_order],
                    ])),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkAction::make('processGrs')
                    ->label('Process GRs')
                    ->icon(Heroicon::OutlinedPlay)
                    ->visible(fn (): bool => auth()->user()?->hasRole(RoleName::Vendor) ?? false)
                    ->modalHeading('Process Selected Purchase Orders')
                    ->modalDescription(function (Collection $records): string {
                        if ($records->pluck('vendor_code')->filter()->unique()->count() > 1) {
                            return 'Select which vendor code to process. Only Purchase Orders for that vendor code are processed.';
                        }

                        return $records->pluck('company_code')->filter()->unique()->count() > 1
                            ? 'Select which company to process. Only Purchase Orders for that company are processed.'
                            : 'Are you sure you want to process the selected Purchase Orders?';
                    })
                    ->modalSubmitActionLabel('Yes, proceed.')
                    ->schema(function (Collection $records): array {
                        $vendorCodes = $records->pluck('vendor_code')->filter()->unique()->values();
                        $companyCodes = $records->pluck('company_code')->filter()->unique();

                        $schema = [];

                        if ($vendorCodes->count() > 1) {
                            $schema[] = Select::make('vendor_code')
                                ->label('Vendor Code to process')
                                ->options(self::vendorOptions($records))
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('company_code', null))
                                ->helperText('Only Purchase Orders for the selected vendor code are processed.');
                        }

                        if ($companyCodes->count() > 1) {
                            $schema[] = Select::make('company_code')
                                ->label('Company to process')
                                ->options(function (Get $get) use ($records): array {
                                    $vendor = $get('vendor_code');

                                    return self::companyOptions(
                                        filled($vendor) ? $records->where('vendor_code', $vendor) : $records,
                                    );
                                })
                                ->required()
                                ->helperText('Only Purchase Orders for the selected company are processed.');
                        }

                        return $schema;
                    })
                    ->deselectRecordsAfterCompletion()
                    ->action(function (array $data, Collection $records) {
                        // A bulk request must stay within one vendor code. A
                        // supplier can own several codes; mixing them in one
                        // submission would post GRs against the wrong vendor.
                        // The modal asks which code to process; this guard is the
                        // server-side backstop when the form is bypassed.
                        $vendorCodes = $records->pluck('vendor_code')->filter()->unique()->values();

                        if ($vendorCodes->count() > 1) {
                            $chosenVendor = $data['vendor_code'] ?? null;

                            if (blank($chosenVendor)) {
                                Notification::make()
                                    ->title('Select a vendor code')
                                    ->body('The selected Purchase Orders belong to multiple vendor codes. Choose one vendor code to process.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $records = $records->where('vendor_code', $chosenVendor);
                        } elseif ($vendorCodes->isNotEmpty()) {
                            $records = $records->where('vendor_code', $vendorCodes->first());
                        }

                        // Single-company selections skip the picker; fall back to
                        // the selection's own company.
                        $companyCode = $data['company_code']
                            ?? $records->pluck('company_code')->filter()->first();

                        if (blank($companyCode)) {
                            Notification::make()
                                ->title('Select a company')
                                ->body('The selected Purchase Orders do not share a company. Choose one company to process.')
                                ->danger()
                                ->send();

                            return;
                        }

                        // Never process across companies: keep only the chosen one.
                        $records = $records->where('company_code', $companyCode);

                        if ($records->isEmpty()) {
                            Notification::make()
                                ->title('Nothing to process')
                                ->body('No selected Purchase Order belongs to the chosen vendor code and company.')
                                ->danger()
                                ->send();

                            return;
                        }

                        // Never mix currencies in one bulk request.
                        $currencies = $records->pluck('currency_key')->filter()->unique();

                        if ($currencies->count() > 1) {
                            Notification::make()
                                ->title('Multiple currencies selected')
                                ->body('The selected Purchase Orders use different currencies ('.
                                    $currencies->implode(', ').
                                    '). Select Purchase Orders with the same currency before processing.')
                                ->danger()
                                ->send();

                            return;
                        }

                        return redirect(GoodsReceiptResource::getUrl('index', [
                            'purchase_orders' => $records->pluck('purchase_order')->values()->all(),
                        ]));
                    }),
            ])
            ->searchPlaceholder('Search PO no. or vendor')
            ->striped();
    }

    protected static function goodsReceiptsUrl($record, Collection $pendingPurchaseOrders): string
    {
        $params = array_merge(
            ['activeTab' => self::defaultTabFor($record, $pendingPurchaseOrders)],
            ['purchase_orders' => [$record->purchase_order]],
            self::grNumbersParam($record->gr_numbers)
        );

        return GoodsReceiptResource::getUrl('index').'?'.http_build_query($params);
    }

    protected static function defaultTabFor($record, Collection $pendingPurchaseOrders): string
    {
        $hasPending = $pendingPurchaseOrders->has($record->purchase_order);

        return $hasPending ? 'pending' : 'all';
    }

    protected static function grNumbersParam(mixed $grNumbers): array
    {
        $cleaned = self::cleanGrNumbers($grNumbers);

        return $cleaned ? ['gr_numbers' => $cleaned] : [];
    }

    protected static function cleanGrNumbers(mixed $grNumbers): array
    {
        $grNumbers = is_array($grNumbers) ? $grNumbers : [$grNumbers];

        return array_values(array_filter($grNumbers, fn ($value) => filled($value)));
    }

    /**
     * Build [code => "CODE — Name"] options for the "Company to process" select
     * from the selected POs, so the confirmation shows a human-readable company.
     *
     * @param  Collection<int, SapPurchaseOrder>  $records
     * @return array<string, string>
     */
    public static function companyOptions(Collection $records): array
    {
        $codes = $records->pluck('company_code')->filter()->unique()->values();

        $names = Company::query()
            ->whereIn('code', $codes)
            ->get(['code', 'name'])
            ->unique('code')
            ->pluck('name', 'code');

        return $codes->mapWithKeys(function (string $code) use ($names): array {
            $name = $names->get($code);

            return [(string) $code => $name ? "{$code} — {$name}" : $code];
        })->all();
    }

    /**
     * Build [code => "CODE — Name"] options for the "Vendor Code to process"
     * select, so the bulk modal shows a human-readable vendor.
     *
     * @param  Collection<int, SapPurchaseOrder>  $records
     * @return array<string, string>
     */
    public static function vendorOptions(Collection $records): array
    {
        return self::vendorCodeLabels($records->pluck('vendor_code')->filter()->unique()->values());
    }

    /**
     * Build [code => "CODE — Name"] options for any set of vendor codes,
     * ordered by vendor name so the select stays readable. Codes with no
     * matching vendor row fall back to the bare code.
     *
     * @param  Collection<int, string>  $codes
     * @return array<string, string>
     */
    protected static function vendorCodeLabels(Collection $codes): array
    {
        if ($codes->isEmpty()) {
            return [];
        }

        $names = Vendor::query()
            ->whereIn('code', $codes->all())
            ->get(['code', 'name'])
            ->unique('code')
            ->pluck('name', 'code');

        return $codes
            ->mapWithKeys(function (string $code) use ($names): array {
                $name = $names->get($code);

                return [(string) $code => $name ? "{$code} — {$name}" : (string) $code];
            })
            // PHP turns numeric-string array keys into ints, so accept both.
            ->sortBy(fn (string $label, int|string $code): string => $names->get($code) ?? (string) $code)
            ->all();
    }

    /**
     * Company options for the table filter.
     *
     * Suppliers get the union of the companies explicitly assigned to them
     * (`company_user`) and the companies that appear on POs for their vendor
     * codes. Admins see every company.
     *
     * @return array<string, string>
     */
    public static function companyFilterOptions(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $query = Company::query()->select('code', 'name');

        if ($user->hasRole(RoleName::Vendor)) {
            $codes = static::supplierCompanyCodes($user);

            if ($codes === []) {
                return [];
            }

            $query->whereIn('code', $codes);
        }

        return $query->orderBy('name')->get()
            ->unique('code')
            ->mapWithKeys(fn (Company $company): array => [(string) $company->code => $company->name])
            ->all();
    }

    /**
     * Vendor options for the table filter.
     *
     * A supplier only ever sees the vendor codes assigned to them. Admins keep
     * the list of every vendor that appears on a PO.
     *
     * @return array<string, string>
     */
    public static function vendorFilterOptions(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $codes = $user->hasRole(RoleName::Vendor)
            ? collect($user->vendorCodes())
            : SapPurchaseOrder::query()
                ->whereNotNull('vendor_code')
                ->distinct()
                ->pluck('vendor_code');

        return self::vendorCodeLabels($codes->filter()->unique()->values());
    }

    /**
     * Companies a supplier may filter by: explicitly assigned codes plus every
     * company code seen on their POs.
     *
     * @return array<int, string>
     */
    protected static function supplierCompanyCodes(User $user): array
    {
        $vendorCodes = $user->vendorCodes();

        if ($vendorCodes === []) {
            return [];
        }

        $assigned = $user->companies()
            ->pluck('companies.code')
            ->filter()
            ->values()
            ->all();

        $fromPurchaseOrders = SapPurchaseOrder::query()
            ->whereIn('vendor_code', $vendorCodes)
            ->whereNotNull('company_code')
            ->distinct()
            ->pluck('company_code')
            ->filter()
            ->values()
            ->all();

        return array_values(array_unique(array_merge($assigned, $fromPurchaseOrders)));
    }
}
