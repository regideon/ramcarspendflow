<?php

namespace App\Filament\My\Pages;

use Closure;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * New RFP: request details (type, due type, payee, purpose), then the items. Two steps
 * on phones; one page with a sticky summary on laptops.
 *
 * Options are sample lists and submitting is a preview until the design is signed
 * off: the form validates like the real one, but nothing is saved.
 */
class CreateRfp extends Page
{
    use WithFileUploads;

    protected static string $layout = 'filament.my.layout';

    protected string $view = 'filament.my.pages.create-rfp';

    protected static ?string $slug = 'requests/new';

    protected static ?string $title = 'New RFP';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<string, array{label: string, description: string, days: int}>
     */
    public const array DUE_TYPES = [
        'standard' => ['label' => 'Standard', 'description' => '5-day processing', 'days' => 5],
        'urgent' => ['label' => 'Urgent', 'description' => '1-day processing', 'days' => 1],
    ];

    /**
     * Supporting documents: PDF, images, Excel, Word or CSV.
     */
    public const string DOCUMENT_TYPES = 'pdf,jpg,jpeg,png,xls,xlsx,doc,docx,csv';

    /**
     * @var array<string, mixed>
     */
    public array $form = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $items = [];

    /**
     * Proof of approval, for a pre-approved RFP.
     */
    public ?TemporaryUploadedFile $proof = null;

    /**
     * The preview result once submitted.
     *
     * @var array{number: string, amount: float}|null
     */
    public ?array $submitted = null;

    public function mount(): void
    {
        $this->form = [
            'requestType' => request()->query('type') === 'reimbursement' ? 'reimbursement' : 'standard',
            'dueType' => 'standard',
            'company' => 'Ramcar Inc.',
            'accountGroup' => 'Vendor',
            'vendor' => '',
            'paymentMethod' => 'Check',
            'purpose' => '',
            'directToVp' => false,
            'preApproved' => false,
        ];

        $this->items = [$this->blankItem()];
    }

    /**
     * Sample lists in the shape the real lookups will have.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            'companies' => ['Ramcar Inc.'],
            'accountGroups' => ['Vendor', 'Plants', 'Non-Trade Vendor', 'Foreign-Trade Vendor', 'Vendor (SCB)', 'Employee Account'],
            'vendors' => [
                'National Book Store' => 'National Book Store · TIN 000-123-456-000',
                'Meralco' => 'Meralco · TIN 000-101-528-000',
                'PLDT Enterprise' => 'PLDT Enterprise · TIN 000-488-793-000',
                'Petron Corporation' => 'Petron Corporation · TIN 000-168-801-000',
                'Cartrack PH' => 'Cartrack PH · TIN 008-765-432-000',
            ],
            'paymentMethods' => ['Check', "Manager's Check", 'Bank Transfer', 'Telegraphic Transfer', 'Post-dated Check'],
            'costCenters' => [
                '101100' => '101100 · Office of the CFO',
                '103100' => '103100 · Controllership',
                '101002' => '101002 · HRM - QC',
                '101006' => '101006 · Ramcar Academy',
            ],
            'categories' => [
                'Supplies' => ['OPEX - Office Supplies', 'OPEX - Computer Supplies', 'Cleaning Supplies', 'Medical Supplies'],
                'Repair & Maintenance' => ['Office Equipment (O&E)', 'OPEX - Building & Structure', 'Air-conditioning Equip (A&E)'],
                'Travel Expense' => ['OPEX - Domestic', 'OPEX - Fuel', 'Per Diem'],
            ],
        ];
    }

    /**
     * "Tue, Nov 4" for each due type, counted in working days from today.
     *
     * @return array<string, string>
     */
    public function dueDates(): array
    {
        return collect(self::DUE_TYPES)
            ->map(fn (array $dueType): string => today()->addWeekdays($dueType['days'])->format('D, M j'))
            ->all();
    }

    /**
     * Who signs, for each kind of RFP. The real route comes from the approval workflow.
     *
     * @return array{matrix: list<string>, vp: list<string>, pre: list<string>}
     */
    public function approvalRoutes(): array
    {
        return [
            'matrix' => ['AP Review', 'Department Head', 'Division Head', 'AP Processing', 'Treasury'],
            'vp' => ['AP Review', 'VP', 'AP Processing', 'Treasury'],
            'pre' => ['AP Review', 'AP Processing', 'Treasury'],
        ];
    }

    public function addItem(): int
    {
        $this->items[] = $this->blankItem();

        return array_key_last($this->items);
    }

    public function removeItem(int $index): void
    {
        if (count($this->items) > 1) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }
    }

    public function readReceipt(int $index): void
    {
        Notification::make()
            ->title('Read receipt')
            ->body('Preview only. Reading the receipt comes when this form is connected.')
            ->info()
            ->send();
    }

    public function saveDraft(): void
    {
        Notification::make()
            ->title('Draft saved')
            ->body('Preview only. Nothing was saved.')
            ->success()
            ->send();
    }

    /**
     * Check step 1 before moving on to the items (phones).
     */
    public function validateDetails(): bool
    {
        $this->validate($this->detailRules(), attributes: $this->attributeNames());

        return true;
    }

    /**
     * Validate everything and show the preview result. On errors, says which step and
     * item to show.
     *
     * @return array{ok: bool, step: int, item: ?int}
     */
    public function submit(): array
    {
        try {
            $this->validate([...$this->detailRules(), ...$this->itemRules()], attributes: $this->attributeNames());
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());

            $keys = array_keys($exception->errors());
            $detailError = collect($keys)->contains(fn (string $key): bool => ! str_starts_with($key, 'items'));
            $itemKey = collect($keys)->first(fn (string $key): bool => preg_match('/^items\.\d+\./', $key) === 1);

            return [
                'ok' => false,
                'step' => $detailError ? 1 : 2,
                'item' => $itemKey === null ? null : (int) explode('.', $itemKey)[1],
            ];
        }

        $this->submitted = [
            'number' => 'RFP-'.now()->year.'-001512',
            'amount' => $this->total(),
        ];

        return ['ok' => true, 'step' => 2, 'item' => null];
    }

    public function total(): float
    {
        return collect($this->items)->sum(fn (array $item): float => self::toAmount($item['amount']));
    }

    public function isReimbursement(): bool
    {
        return $this->form['requestType'] === 'reimbursement';
    }

    public static function toAmount(mixed $value): float
    {
        return (float) str_replace(',', '', (string) $value);
    }

    /**
     * @return array<string, mixed>
     */
    protected function detailRules(): array
    {
        $standard = ! $this->isReimbursement();

        return [
            'form.requestType' => ['required', Rule::in(['standard', 'reimbursement'])],
            'form.dueType' => ['required', Rule::in(array_keys(self::DUE_TYPES))],
            'form.company' => ['required', Rule::in($this->options()['companies'])],
            'form.accountGroup' => [Rule::requiredIf($standard), 'nullable', Rule::in($this->options()['accountGroups'])],
            'form.vendor' => [Rule::requiredIf($standard), 'nullable', Rule::in(array_keys($this->options()['vendors']))],
            'form.paymentMethod' => ['required', Rule::in($this->options()['paymentMethods'])],
            'form.purpose' => ['required', 'string', 'max:255'],
            'form.directToVp' => ['boolean'],
            'form.preApproved' => ['boolean'],
            'proof' => [Rule::requiredIf((bool) $this->form['preApproved']), 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function itemRules(): array
    {
        $options = $this->options();
        $rules = ['items' => ['required', 'array', 'min:1']];

        foreach ($this->items as $index => $item) {
            $prefix = "items.{$index}";
            $oneTime = $this->isReimbursement() && $item['vendorType'] === 'one_time';

            $rules += [
                "{$prefix}.upload" => ['required', 'file', 'mimes:'.self::DOCUMENT_TYPES, 'max:10240'],
                "{$prefix}.description" => ['required', 'string', 'max:150'],
                "{$prefix}.chargeTo" => ['required', Rule::in(['cost_center', 'po'])],
                "{$prefix}.costCenter" => [Rule::requiredIf($item['chargeTo'] === 'cost_center'), 'nullable', Rule::in(array_keys($options['costCenters']))],
                "{$prefix}.po" => [Rule::requiredIf($item['chargeTo'] === 'po'), 'nullable', 'string', 'max:30'],
                "{$prefix}.category" => ['required', Rule::in(array_keys($options['categories']))],
                "{$prefix}.subcategory" => ['required', Rule::in($options['categories'][$item['category']] ?? [])],
                "{$prefix}.invoiceNo" => ['required', 'string', 'max:50'],
                "{$prefix}.invoiceDate" => ['required', 'date', 'before_or_equal:today'],
                "{$prefix}.amount" => ['required', fn (string $attribute, mixed $value, Closure $fail) => is_numeric(str_replace(',', '', (string) $value)) && self::toAmount($value) > 0 ? null : $fail('Enter an amount above zero.')],
                "{$prefix}.vendorCompany" => [Rule::requiredIf($this->isReimbursement()), 'nullable', Rule::in($options['companies'])],
                "{$prefix}.vendorType" => [Rule::requiredIf($this->isReimbursement()), 'nullable', Rule::in(['vendor', 'one_time'])],
                "{$prefix}.vendor" => [Rule::requiredIf($this->isReimbursement() && $item['vendorType'] === 'vendor'), 'nullable', Rule::in(array_keys($options['vendors']))],
                "{$prefix}.oneTimeName" => [Rule::requiredIf($oneTime), 'nullable', 'string', 'max:150'],
                "{$prefix}.oneTimeTin" => [Rule::requiredIf($oneTime), 'nullable', 'string', 'max:15'],
                "{$prefix}.oneTimeAddress" => [Rule::requiredIf($oneTime), 'nullable', 'string', 'max:255'],
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function attributeNames(): array
    {
        return [
            'form.company' => 'paying company',
            'form.accountGroup' => 'account group',
            'form.vendor' => 'vendor',
            'form.paymentMethod' => 'payment method',
            'form.purpose' => 'purpose',
            'proof' => 'proof of approval',
            'items.*.upload' => 'supporting document',
            'items.*.description' => 'item details',
            'items.*.costCenter' => 'cost center',
            'items.*.po' => 'PO number',
            'items.*.category' => 'category',
            'items.*.subcategory' => 'sub category',
            'items.*.invoiceNo' => 'invoice no.',
            'items.*.invoiceDate' => 'invoice date',
            'items.*.amount' => 'amount',
            'items.*.vendorCompany' => 'vendor company',
            'items.*.vendor' => 'vendor',
            'items.*.oneTimeName' => 'name',
            'items.*.oneTimeTin' => 'TIN',
            'items.*.oneTimeAddress' => 'address',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function blankItem(): array
    {
        return [
            'upload' => null,
            'description' => '',
            'chargeTo' => 'cost_center',
            'costCenter' => '',
            'po' => '',
            'category' => '',
            'subcategory' => '',
            'invoiceNo' => '',
            'invoiceDate' => '',
            'amount' => '',
            'vendorCompany' => 'Ramcar Inc.',
            'vendorType' => 'vendor',
            'vendor' => '',
            'oneTimeName' => '',
            'oneTimeTin' => '',
            'oneTimeAddress' => '',
        ];
    }

    /**
     * Clear the sub category when the category changes, since its options depend on it.
     */
    public function updated(string $property): void
    {
        if (preg_match('/^items\.(\d+)\.category$/', $property, $matches) === 1) {
            $this->items[(int) $matches[1]]['subcategory'] = '';
        }
    }
}
