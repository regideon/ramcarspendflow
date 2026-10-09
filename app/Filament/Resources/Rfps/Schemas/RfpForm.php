<?php

namespace App\Filament\Resources\Rfps\Schemas;

use App\Enums\RfpRequestType;
use App\Enums\RfpVendorType;
use App\Models\AccountGroup;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\PaymentMethod;
use App\Models\Rfp\ApprovalRule;
use App\Models\Rfp\ApprovalRuleStep;
use App\Models\Rfp\DueType;
use App\Models\Rfp\Subcategory;
use App\Models\Vendor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * The legacy RFP Portal request form (RFP info, items, totals), rebuilt compact.
 */
class RfpForm
{
    /**
     * Fixed accounting steps around the Step 3 matrix (docs/03-rfp-approval-flow.md).
     */
    public const array ACCOUNTING_TAIL = ['AP Processor', 'AP Supervisor', 'AP Manager', 'Treasury'];

    /**
     * Supporting documents: PDF, images, CSV and Excel, as on the legacy form.
     */
    public const array DOCUMENT_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'text/csv',
        'application/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                static::infoSection(),
                static::itemsSection(),
                static::summarySection(),
            ]);
    }

    protected static function infoSection(): Section
    {
        return Section::make('RFP info')
            ->compact()
            ->columns(['default' => 1, 'sm' => 2, 'xl' => 4])
            ->schema([
                ToggleButtons::make('rfp_due_type_id')
                    ->label('Due type')
                    ->options(fn (): array => static::dueTypes()->pluck('name', 'id')->all())
                    ->default(fn (): ?int => static::dueTypes()->firstWhere('is_default', true)?->id)
                    ->grouped()
                    ->required()
                    ->live()
                    ->belowContent(function (mixed $state): ?string {
                        $dueType = static::dueTypes()->firstWhere('id', (int) $state);

                        return $dueType ? "{$dueType->description} · due {$dueType->dueDateFrom()->format('D, M j')}" : null;
                    }),
                ToggleButtons::make('request_type')
                    ->label('RFP type')
                    ->options(RfpRequestType::class)
                    ->default(RfpRequestType::Standard)
                    ->grouped()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('vendor_id', null);
                        $set('payment_method_id', null);
                    }),
                Toggle::make('is_direct_to_vp')
                    ->label('Direct to VP')
                    ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: 'Only for RFPs that need VP approval only.')
                    ->inline(false)
                    ->live()
                    ->disabled(fn (Get $get): bool => (bool) $get('is_pre_approved'))
                    ->afterStateUpdated(fn (Set $set, $state) => $state ? $set('is_pre_approved', false) : null),
                Toggle::make('is_pre_approved')
                    ->label('Pre-approved')
                    ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: 'Already approved outside the portal. Skips the approval matrix; attach the proof of approval.')
                    ->inline(false)
                    ->live()
                    ->disabled(fn (Get $get): bool => (bool) $get('is_direct_to_vp'))
                    ->afterStateUpdated(fn (Set $set, $state) => $state ? $set('is_direct_to_vp', false) : null),
                FileUpload::make('pre_approval_documents')
                    ->label('Proof of approval')
                    ->helperText('The approval email or other proof. PDF or image, up to 10 MB each.')
                    ->multiple()
                    ->disk('local')
                    ->directory('rfp-pre-approvals')
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->maxSize(10_240)
                    ->maxFiles(10)
                    ->downloadable()
                    ->openable()
                    ->panelLayout('grid')
                    ->visible(fn (Get $get): bool => (bool) $get('is_pre_approved'))
                    ->required(fn (Get $get): bool => (bool) $get('is_pre_approved'))
                    ->columnSpanFull(),
                Select::make('company_id')
                    ->label('Paying company')
                    ->options(fn (): array => auth()->user()->companies()->orderBy('code')->pluck('name', 'companies.id')->all())
                    ->default(fn (): ?int => auth()->user()->company_id)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('vendor_id', null);
                        $set('payment_method_id', null);
                    }),
                Select::make('account_group_id')
                    ->label('Account group')
                    ->options(fn (): array => AccountGroup::query()->where('is_active', true)->orderBy('description')->pluck('description', 'id')->all())
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('vendor_id', null);
                        $set('payment_method_id', null);
                    }),
                Select::make('vendor_id')
                    ->label(fn (Get $get): string => static::isReimbursement($get) ? 'Final payee' : 'Payee')
                    ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: 'Not in the list? Ask AP to extend the vendor to the paying company.')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => static::vendorOptions(
                        static::payeeQuery($get('company_id'), $get('account_group_id'))
                            ->where(fn ($query) => $query->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")),
                    ))
                    ->options(fn (Get $get): array => static::vendorOptions(static::payeeQuery($get('company_id'), $get('account_group_id'))))
                    ->getOptionLabelUsing(fn ($value): ?string => static::vendorLabel(Vendor::find($value)))
                    ->disabled(fn (Get $get): bool => blank($get('company_id')) || blank($get('account_group_id')))
                    ->placeholder(fn (Get $get): string => blank($get('account_group_id')) ? 'Choose an account group first' : 'Select')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('payment_method_id', null)),
                Select::make('payment_method_id')
                    ->label('Payment method')
                    ->options(fn (Get $get): array => static::paymentMethodOptions($get))
                    ->placeholder(fn (Get $get): string => ! static::isReimbursement($get) && blank($get('vendor_id')) ? 'Choose the payee first' : 'Select')
                    ->required(),
                Textarea::make('purpose')
                    ->required()
                    ->rows(2)
                    ->autosize()
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }

    protected static function itemsSection(): Section
    {
        return Section::make('Items')
            ->description('Enter each line item within the RFP.')
            ->compact()
            ->schema([
                Repeater::make('items')
                    ->hiddenLabel()
                    ->relationship()
                    ->orderColumn('sort')
                    // ->compact()

                    // ->extraAttributes(['class' => 'sf-sheet']) // custom CSS off for now
                    // Cells are matched to columns by position, so the Reimbursement-only
                    // fields come last and their columns are added only on a Reimbursement.
                    ->table(fn (Get $get): array => [
                        // Charging first (AP request 2026-10-07): cost center or PO, then the sub category
                        TableColumn::make('Item details')->markAsRequired()->width('16%'),
                        TableColumn::make('Cost center')->width('14%'),
                        TableColumn::make('PO no.')->width('8%'),
                        TableColumn::make('Sub category')->markAsRequired()->width('15%'),
                        TableColumn::make('Invoice no.')->markAsRequired()->width('9%'),
                        TableColumn::make('Invoice date')->markAsRequired()->width('10%'),
                        TableColumn::make('Currency')->markAsRequired()->width('7%'),
                        TableColumn::make('Amount')->markAsRequired()->alignment(Alignment::End)->width('9%'),
                        TableColumn::make('PHP')->alignment(Alignment::End)->width('9%'),
                        TableColumn::make('Documents')->markAsRequired(),
                        ...(static::isReimbursement($get) ? [
                            TableColumn::make('Vendor company')->markAsRequired()->width('11%'),
                            TableColumn::make('Paid to')->markAsRequired()->width('9%'),
                            TableColumn::make('Vendor')->markAsRequired()->width('13%'),
                        ] : []),
                    ])
                    ->schema([
                        TextInput::make('description')
                            ->required()
                            ->maxLength(150),
                        Select::make('cost_center_id')
                            ->options(fn (Get $get): array => static::costCenterOptions($get('../../company_id')))
                            ->searchable()
                            ->placeholder(fn (Get $get): string => filled($get('po_number')) ? 'n/a' : 'Select')
                            ->disabled(fn (Get $get): bool => filled($get('po_number')))
                            ->dehydrated()
                            ->required(fn (Get $get): bool => blank($get('po_number')))
                            ->validationMessages(['required' => 'Choose a cost center or enter a PO no.'])
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::clearSubcategoryIfNotAllowed($get, $set)),
                        TextInput::make('po_number')
                            ->maxLength(15)
                            ->placeholder(fn (Get $get): string => filled($get('cost_center_id')) ? 'n/a' : 'or PO no.')
                            ->disabled(fn (Get $get): bool => filled($get('cost_center_id')))
                            ->dehydrated()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::clearSubcategoryIfNotAllowed($get, $set)),
                        Select::make('rfp_subcategory_id')
                            ->options(fn (Get $get): array => static::subcategoryOptions($get('cost_center_id'), $get('po_number')))
                            ->in(fn (Get $get): array => static::allowedSubcategoryIds($get('cost_center_id'), $get('po_number')))
                            ->validationMessages(['in' => 'This sub category is not allowed for the cost center or PO.'])
                            ->searchable()
                            ->placeholder(fn (Get $get): string => blank($get('cost_center_id')) && blank($get('po_number')) ? 'Cost center or PO first' : 'Select')
                            ->required(),
                        TextInput::make('invoice_number')
                            ->required()
                            ->maxLength(50),
                        DatePicker::make('invoice_date')
                            ->required()
                            ->minDate(fn () => today()->subMonths(6))
                            ->maxDate(fn () => today()->addDay()),
                        Select::make('currency_id')
                            ->options(fn (): array => static::currencyOptions())
                            ->default(fn (): ?int => array_search('PHP', static::currencyOptions(), true) ?: null)
                            ->searchable()
                            ->required()
                            ->live(),
                        TextInput::make('amount')
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue(999_999_999)
                            ->step(0.01)
                            ->inputMode('decimal')
                            ->required()
                            ->extraInputAttributes(['class' => 'text-end tabular-nums'])
                            ->live(onBlur: true),
                        TextEntry::make('php_amount_preview')
                            ->hiddenLabel()
                            ->state(fn (Get $get): ?string => static::linePhpAmount($get('currency_id'), $get('amount')))
                            ->tooltip(fn (Get $get): ?string => static::rateHint($get('currency_id')))
                            ->color(fn (Get $get): ?string => filled($get('currency_id')) && static::rateToPhp($get('currency_id')) === null ? 'danger' : 'gray')
                            // ->extraAttributes(['class' => 'sf-cell-text text-end tabular-nums']) // custom CSS off for now
                            ->placeholder('—'),
                        FileUpload::make('attachments')
                            ->placeholder('Attach invoice, receipt…')
                            ->multiple()
                            ->disk('local')
                            ->directory('rfp-attachments')
                            ->visibility('private')
                            ->acceptedFileTypes(self::DOCUMENT_TYPES)
                            ->maxSize(10_240)
                            ->maxFiles(10)
                            ->downloadable()
                            ->openable()
                            ->imagePreviewHeight('48'),
                        // Reimbursement only (see the table columns above)
                        Select::make('company_id')
                            ->options(fn (): array => Company::query()->orderBy('code')->get()->mapWithKeys(fn (Company $company): array => [$company->id => $company->short_name ?? $company->name])->all())
                            ->default(fn (Get $get): mixed => $get('../../company_id'))
                            ->visible(fn (Get $get): bool => static::isReimbursement($get, '../../request_type'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('vendor_id', null)),
                        Select::make('vendor_type')
                            ->options(RfpVendorType::class)
                            ->default(RfpVendorType::Vendor)
                            ->selectablePlaceholder(false)
                            ->visible(fn (Get $get): bool => static::isReimbursement($get, '../../request_type'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('vendor_id', null);
                                $set('rfp_one_time_vendor_id', null);
                            }),
                        Group::make([
                            Select::make('vendor_id')
                                ->hiddenLabel()
                                ->searchable()
                                ->getSearchResultsUsing(fn (string $search, Get $get): array => static::vendorOptions(
                                    static::vendorQuery($get('company_id'))
                                        ->where(fn ($query) => $query->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")),
                                ))
                                ->options(fn (Get $get): array => static::vendorOptions(static::vendorQuery($get('company_id'))))
                                ->getOptionLabelUsing(fn ($value): ?string => static::vendorLabel(Vendor::find($value)))
                                ->visible(fn (Get $get): bool => static::enumValue($get('vendor_type')) === RfpVendorType::Vendor->value)
                                ->required()
                                ->validationAttribute('vendor'),
                            Select::make('rfp_one_time_vendor_id')
                                ->hiddenLabel()
                                ->relationship('oneTimeVendor', 'name')
                                ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->name} · TIN {$record->tin}")
                                ->searchable(['name', 'tin'])
                                ->createOptionForm([
                                    TextInput::make('title')->maxLength(150),
                                    TextInput::make('name')->required()->maxLength(150),
                                    TextInput::make('tin')->label('TIN')->required()->maxLength(15),
                                    TextInput::make('street')->maxLength(150),
                                    TextInput::make('city')->maxLength(150),
                                ])
                                ->createOptionAction(fn ($action) => $action->modalWidth('md'))
                                ->visible(fn (Get $get): bool => static::enumValue($get('vendor_type')) === RfpVendorType::OneTimeVendor->value)
                                ->required()
                                ->validationAttribute('one-time vendor'),
                        ])
                            ->visible(fn (Get $get): bool => static::isReimbursement($get, '../../request_type')),
                    ])
                    ->defaultItems(1)
                    ->minItems(1)
                    ->cloneable()
                    ->reorderableWithButtons()
                    ->addActionLabel('Add item')
                    ->addActionAlignment(Alignment::Start),
            ]);
    }

    protected static function summarySection(): Section
    {
        return Section::make()
            ->compact()
            ->schema([
                Grid::make(['default' => 2, 'xl' => 4])
                    ->schema([
                        TextEntry::make('summary_total')
                            ->label('Total amount')
                            ->state(function (Get $get): string {
                                $phpTotal = static::phpTotal($get('items'));

                                return $phpTotal === null
                                    ? 'No exchange rate for '.implode(', ', static::currenciesWithoutRate($get('items')))
                                    : 'PHP '.number_format((float) $phpTotal, 2);
                            })
                            ->color(fn (Get $get): ?string => static::phpTotal($get('items')) === null ? 'danger' : null)
                            ->weight('bold'),
                        TextEntry::make('summary_foreign')
                            ->label('Foreign currency')
                            ->state(fn (Get $get): array => collect(static::foreignTotals($get('items')))
                                ->map(fn (string $amount, string $code): string => $code.' '.number_format((float) $amount, 2))
                                ->values()
                                ->all())
                            ->listWithLineBreaks()
                            ->placeholder('—'),
                        TextEntry::make('summary_route')
                            ->label('Approval route')
                            ->state(fn (Get $get): string => static::approvalRoute(
                                RfpRequestType::tryFrom((string) static::enumValue($get('request_type'))),
                                static::phpTotal($get('items')) ?? '0',
                                (bool) $get('is_direct_to_vp'),
                                (bool) $get('is_pre_approved'),
                            ))
                            ->columnSpan(['default' => 2]),
                    ]),
            ]);
    }

    /**
     * Full route preview: AP Processor → matrix approvers → accounting tail.
     */
    public static function approvalRoute(?RfpRequestType $requestType, string $amountInPhp, bool $isDirectToVp, bool $isPreApproved = false): string
    {
        if ($isPreApproved) {
            return implode(' → ', ['AP Processor', 'Pre-approved (no matrix)', ...self::ACCOUNTING_TAIL]);
        }

        $rule = $requestType ? ApprovalRule::findFor($requestType, $amountInPhp, $isDirectToVp) : null;

        $matrix = $rule === null
            ? '(no matching tier)'
            : $rule->steps
                ->groupBy('step_order')
                ->map(fn ($steps): string => $steps->map(fn (ApprovalRuleStep $step): string => $step->role->name)->implode(' + '))
                ->implode(' → ');

        return implode(' → ', ['AP Processor', $matrix, ...self::ACCOUNTING_TAIL]);
    }

    /**
     * Rate to PHP for a currency, or null when none is on file.
     */
    public static function rateToPhp(mixed $currencyId): ?string
    {
        if (blank($currencyId)) {
            return null;
        }

        return once(function () use ($currencyId): ?string {
            $currency = Currency::find($currencyId);

            return $currency ? ExchangeRate::rateToPhp($currency) : null;
        });
    }

    /**
     * Sum of the lines in PHP, or null when a line's currency has no rate.
     *
     * @param  array<int|string, array<string, mixed>>|null  $items
     */
    public static function phpTotal(?array $items): ?string
    {
        $total = '0.00';

        foreach ($items ?? [] as $item) {
            if (! is_numeric($item['amount'] ?? null)) {
                continue;
            }

            $rate = static::rateToPhp($item['currency_id'] ?? null);

            if ($rate === null) {
                return null;
            }

            $total = bcadd($total, bcadd(bcmul((string) $item['amount'], $rate, 6), '0.005', 2), 2);
        }

        return $total;
    }

    /**
     * Line amounts per foreign currency, e.g. ['USD' => '1200.00'].
     *
     * @param  array<int|string, array<string, mixed>>|null  $items
     * @return array<string, string>
     */
    public static function foreignTotals(?array $items): array
    {
        return collect($items ?? [])
            ->filter(fn (array $item): bool => is_numeric($item['amount'] ?? null) && filled($item['currency_id'] ?? null))
            ->groupBy(fn (array $item): string => static::currencyOptions()[$item['currency_id']] ?? '?')
            ->reject(fn ($lines, string $code): bool => $code === 'PHP')
            ->map(fn ($lines): string => $lines->reduce(fn (string $sum, array $item): string => bcadd($sum, (string) $item['amount'], 2), '0.00'))
            ->all();
    }

    /**
     * @param  array<int|string, array<string, mixed>>|null  $items
     * @return list<string>
     */
    protected static function currenciesWithoutRate(?array $items): array
    {
        return collect($items ?? [])
            ->filter(fn (array $item): bool => is_numeric($item['amount'] ?? null) && static::rateToPhp($item['currency_id'] ?? null) === null)
            ->map(fn (array $item): string => static::currencyOptions()[$item['currency_id'] ?? null] ?? 'a line without currency')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * PHP equivalent of a line, or null when there is no amount or no rate.
     */
    protected static function linePhpAmount(mixed $currencyId, mixed $amount): ?string
    {
        $rate = static::rateToPhp($currencyId);

        if (! is_numeric($amount) || $rate === null) {
            return null;
        }

        return number_format((float) bcadd(bcmul((string) $amount, $rate, 6), '0.005', 2), 2);
    }

    protected static function rateHint(mixed $currencyId): ?string
    {
        $code = static::currencyOptions()[$currencyId] ?? null;

        if ($code === null || $code === 'PHP') {
            return null;
        }

        $rate = static::rateToPhp($currencyId);

        return $rate === null ? "No {$code} rate on file" : "1 {$code} = ".rtrim(rtrim($rate, '0'), '.').' PHP';
    }

    /**
     * Sub categories the line may be charged to, grouped by category ("charging first"):
     * - with a cost center: G/L accounts mapped to it that match its OPEX/MFG type;
     * - with a PO no.: sub categories that take a PO (e.g. Supplier Down Payment).
     *
     * @return array<string, array<int, string>>
     */
    public static function subcategoryOptions(mixed $costCenterId, mixed $poNumber = null): array
    {
        if (blank($costCenterId) && blank($poNumber)) {
            return [];
        }

        return once(function () use ($costCenterId): array {
            $costCenter = filled($costCenterId) ? CostCenter::find($costCenterId) : null;

            return Subcategory::query()
                ->with(['category', 'glAccount'])
                ->where('is_active', true)
                ->whereHas('category', fn ($query) => $query->where('is_active', true))
                ->when(
                    $costCenter,
                    fn ($query) => $query->where('requires_po_number', false)->whereHas('glAccount.costCenters', fn ($query) => $query
                        ->whereKey($costCenter->id)
                        ->where('cost_center_gl_account.is_active', true)),
                    fn ($query) => $query->where('requires_po_number', true),
                )
                ->get()
                ->filter(fn (Subcategory $subcategory): bool => $costCenter?->allowsGlCode($subcategory->glAccount->code) ?? true)
                ->sortBy(['category.name', 'name'])
                ->groupBy('category.name')
                ->map(fn ($subcategories) => $subcategories->mapWithKeys(fn (Subcategory $subcategory): array => [
                    $subcategory->id => "{$subcategory->name} · {$subcategory->glAccount->code}",
                ])->all())
                ->all();
        });
    }

    /**
     * @return list<int>
     */
    public static function allowedSubcategoryIds(mixed $costCenterId, mixed $poNumber = null): array
    {
        return collect(static::subcategoryOptions($costCenterId, $poNumber))->flatMap(fn (array $group): array => array_keys($group))->all();
    }

    protected static function clearSubcategoryIfNotAllowed(Get $get, Set $set): void
    {
        // Nothing to charge to yet: keep the choice; validation flags it if it stays that way
        if (blank($get('cost_center_id')) && blank($get('po_number'))) {
            return;
        }

        if (! in_array((int) $get('rfp_subcategory_id'), static::allowedSubcategoryIds($get('cost_center_id'), $get('po_number')), true)) {
            $set('rfp_subcategory_id', null);
        }
    }

    /**
     * Active cost centers of the paying company, labelled OPEX or MFG.
     *
     * @return array<int, string>
     */
    public static function costCenterOptions(mixed $companyId): array
    {
        if (blank($companyId)) {
            return [];
        }

        return once(fn (): array => CostCenter::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (CostCenter $costCenter): array => [$costCenter->id => trim(sprintf(
                '%s · %s %s',
                $costCenter->code,
                $costCenter->description,
                match ($costCenter->isManufacturing()) {
                    true => '(MFG)',
                    false => '(OPEX)',
                    null => '',
                },
            ))])
            ->all());
    }

    /**
     * Payment methods allowed for the payee in the paying company; any active method on a Reimbursement.
     *
     * @return array<int, string>
     */
    public static function paymentMethodOptions(Get $get): array
    {
        $query = PaymentMethod::query()->where('is_active', true)->orderBy('description');

        if (! static::isReimbursement($get)) {
            $codes = Vendor::find($get('vendor_id'))
                ?->companies()
                ->whereKey($get('company_id'))
                ->first()
                ?->pivot
                ->payment_methods;

            if (blank($codes)) {
                return [];
            }

            $query->whereIn('code', str_split($codes));
        }

        return $query->pluck('description', 'id')->all();
    }

    /**
     * @return array<int, string> currency codes keyed by id
     */
    public static function currencyOptions(): array
    {
        return once(fn (): array => Currency::query()->where('is_active', true)->orderBy('code')->pluck('code', 'id')->all());
    }

    /**
     * @return Collection<int, DueType>
     */
    protected static function dueTypes()
    {
        return once(fn () => DueType::query()->active()->get());
    }

    /**
     * Active vendors extended to the company (all active vendors when no company is set).
     */
    protected static function vendorQuery(mixed $companyId)
    {
        return Vendor::query()
            ->where('is_active', true)
            ->when($companyId, fn ($query) => $query->whereHas('companies', fn ($query) => $query
                ->whereKey($companyId)
                ->where('company_vendor.is_active', true)))
            ->orderBy('name');
    }

    protected static function payeeQuery(mixed $companyId, mixed $accountGroupId)
    {
        return static::vendorQuery($companyId)->where('account_group_id', $accountGroupId ?: 0);
    }

    /**
     * @return array<int, string>
     */
    protected static function vendorOptions($query): array
    {
        return $query->limit(50)->get()->mapWithKeys(fn (Vendor $vendor): array => [$vendor->id => static::vendorLabel($vendor)])->all();
    }

    protected static function vendorLabel(?Vendor $vendor): ?string
    {
        return $vendor ? "{$vendor->code} · {$vendor->name}" : null;
    }

    protected static function isReimbursement(Get $get, string $path = 'request_type'): bool
    {
        return static::enumValue($get($path)) === RfpRequestType::Reimbursement->value;
    }

    protected static function enumValue(mixed $state): mixed
    {
        return $state instanceof \BackedEnum ? $state->value : $state;
    }
}
