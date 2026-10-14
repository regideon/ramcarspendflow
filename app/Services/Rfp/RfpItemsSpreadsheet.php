<?php

namespace App\Services\Rfp;

use App\Enums\RfpVendorType;
use App\Filament\Resources\Rfps\Schemas\RfpForm;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\Rfp\OneTimeVendor;
use App\Models\Rfp\Subcategory;
use App\Models\Vendor;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer;
use Throwable;

/**
 * Mass upload of RFP items: the Excel template (with master data sheets) and the
 * parser that turns a filled-in sheet into Items repeater rows.
 *
 * Columns are matched by header name, so the legacy RFP Portal template (A–I, plus
 * Vendor Type / Vendor) also works. "GL Number" picks the Sub Category.
 */
class RfpItemsSpreadsheet
{
    /**
     * Template columns, in order: header => what to enter.
     *
     * @var array<string, string>
     */
    public const array COLUMNS = [
        'Invoice Number' => 'Required. The OR / SI / bill number, or N/A.',
        'Invoice Date' => 'Required. MM/DD/YYYY (e.g. 07/31/2026) or an Excel date. Within the last 6 months.',
        'Item Details' => 'Required. What the item is, e.g. Transportation - Toll.',
        'GL Number' => 'Required. Picks the Sub Category; see the "Sub Categories" sheet.',
        'Cost Center' => 'Cost center code (see "Cost Centers"), or N/A when you enter a PO Number. OPEX cost centers (3rd digit 1-2) take GL 6..., MFG (3rd digit 3+) take GL 5....',
        'PO Number' => 'Only for PO-type sub categories (e.g. Supplier Down Payment); otherwise N/A.',
        'Company' => 'Company code or short name (see "Companies"). Blank = the paying company. On a Reimbursement, the company of the vendor you paid.',
        'Vendor Type' => 'Reimbursement only: Vendor or One-time vendor (see "Vendor Types"). Blank = Vendor.',
        'Vendor' => 'Reimbursement only: vendor code or name (see "Vendors"), or one-time vendor name or TIN (see "One-time Vendors"). May be left blank and chosen on screen.',
        'Currency' => 'Required. Currency code, e.g. PHP, USD (see "Currencies").',
        'Amount' => 'Required. Amount in that currency, greater than 0.',
    ];

    /**
     * Most rows read from one file.
     */
    public const int MAX_ROWS = 500;

    /**
     * Build the template workbook and return its temporary path.
     */
    public function template(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rfp-items-').'.xlsx';
        $bold = (new Style)->setFontBold();
        $header = (new Style)->setFontBold()->setBackgroundColor('FDE68A');

        $writer = new Writer;
        $writer->openToFile($path);

        $sheet = $writer->getCurrentSheet()->setName('Items');
        $writer->addRow(Row::fromValues(array_keys(self::COLUMNS), $header));
        $sheet->setColumnWidth(18, 1, 2, 4, 5, 6, 7, 8, 10, 11);
        $sheet->setColumnWidth(40, 3, 9);

        $this->addSheet($writer, 'Instructions', ['Column', 'What to enter'], collect(self::COLUMNS)->map(fn (string $help, string $column): array => [$column, $help])->values(), $header, [24, 110]);
        $writer->addRows([
            Row::fromValues([]),
            Row::fromValues(['Example row'], $bold),
            Row::fromValues(array_keys(self::COLUMNS), $bold),
            Row::fromValues(['12629', '07/31/2026', 'Transportation - Gas', '6006000', '112122', 'N/A', 'OMMC', 'Vendor', '', 'PHP', 2500]),
            Row::fromValues([]),
            Row::fromValues(['Attach a supporting document to each item on screen after uploading; it is required before submitting.']),
        ]);

        $this->addSheet($writer, 'Companies', ['Company Code', 'Short Name', 'Name'], Company::query()->where('is_active', true)->orderBy('code')->get()
            ->map(fn (Company $company): array => [$company->code, $company->short_name, $company->name]), $header, [14, 14, 40]);

        $this->addSheet($writer, 'Sub Categories', ['GL Number', 'Sub Category', 'Category', 'Needs Cost Center', 'Needs PO Number', 'Expense Type'], RfpForm::activeSubcategories()
            ->sortBy(fn (Subcategory $subcategory): string => $subcategory->glAccount->code.$subcategory->name)
            ->map(fn (Subcategory $subcategory): array => [
                $subcategory->glAccount->code,
                $subcategory->name,
                $subcategory->category->name,
                $subcategory->requires_cost_center ? 'Yes' : 'No',
                $subcategory->requires_po_number ? 'Yes' : 'No',
                match ($subcategory->glAccount->code[0] ?? '') {
                    '5' => 'MFG',
                    '6' => 'OPEX',
                    default => '',
                },
            ]), $header, [14, 40, 30, 18, 16, 14]);

        $this->addSheet($writer, 'Cost Centers', ['Company Code', 'Cost Center', 'Description', 'Expense Type', 'Usual GL Numbers'], CostCenter::query()
            ->with(['company', 'glAccounts' => fn ($query) => $query->wherePivot('is_active', true)->orderBy('code')])
            ->where('is_active', true)
            ->orderBy('company_id')->orderBy('code')
            ->get()
            ->map(fn (CostCenter $costCenter): array => [
                $costCenter->company?->code,
                $costCenter->code,
                $costCenter->description,
                match ($costCenter->isManufacturing()) {
                    true => 'MFG',
                    false => 'OPEX',
                    null => '',
                },
                $costCenter->glAccounts->pluck('code')->implode(', '),
            ]), $header, [14, 14, 40, 14, 80]);

        $this->addSheet($writer, 'Vendor Types', ['Vendor Type', 'When to use'], collect([
            [RfpVendorType::Vendor->getLabel(), 'The vendor is in the vendor master ("Vendors" sheet). You may also write: Vendor'],
            [RfpVendorType::OneTimeVendor->getLabel(), 'A payee not in the vendor master ("One-time Vendors" sheet). You may also write: OTV'],
        ]), $header, [20, 90]);

        $this->addSheet($writer, 'Vendors', ['Vendor Code', 'Vendor Name', 'Account Group', 'Companies'], Vendor::query()
            ->with(['accountGroup', 'companies' => fn ($query) => $query->wherePivot('is_active', true)])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Vendor $vendor): array => [$vendor->code, $vendor->name, $vendor->accountGroup?->description, $vendor->companies->pluck('code')->implode(', ')]), $header, [14, 50, 24, 24]);

        $this->addSheet($writer, 'One-time Vendors', ['Name', 'TIN', 'City'], OneTimeVendor::query()->orderBy('name')->get()
            ->map(fn (OneTimeVendor $vendor): array => [$vendor->name, $vendor->tin, $vendor->city]), $header, [40, 20, 24]);

        $this->addSheet($writer, 'Currencies', ['Currency', 'Name'], Currency::query()->where('is_active', true)->orderBy('code')->get()
            ->map(fn (Currency $currency): array => [$currency->code, $currency->name]), $header, [12, 40]);

        $writer->close();

        return $path;
    }

    /**
     * Read a filled-in template into Items repeater rows. All rows must be valid:
     * when any row fails, no items are returned and every problem is listed.
     *
     * @return array{items: list<array<string, mixed>>, errors: list<string>}
     */
    public function parse(string $path, string $extension, int $payingCompanyId, bool $isReimbursement): array
    {
        try {
            $rows = $this->readRows($path, Str::lower($extension));
        } catch (Throwable) {
            return ['items' => [], 'errors' => ['The file could not be read. Upload the .xlsx template or a .csv file.']];
        }

        $headerRow = array_shift($rows) ?? [];
        $columns = $this->columnIndexes($headerRow);

        $missing = array_diff(['Invoice Number', 'Invoice Date', 'Item Details', 'GL Number', 'Currency', 'Amount'], array_keys($columns));

        if ($missing !== []) {
            return ['items' => [], 'errors' => ['Missing column(s): '.implode(', ', $missing).'. Use the template (Download template).']];
        }

        $rows = array_filter($rows, fn (array $row): bool => collect($row)->contains(fn ($cell): bool => filled($this->text($cell))));

        if ($rows === []) {
            return ['items' => [], 'errors' => ['The file has no item rows.']];
        }

        if (count($rows) > self::MAX_ROWS) {
            return ['items' => [], 'errors' => ['Upload at most '.self::MAX_ROWS.' rows at a time.']];
        }

        $payingCompany = Company::find($payingCompanyId);
        $items = [];
        $errors = [];

        foreach ($rows as $index => $cells) {
            $value = fn (string $column): mixed => isset($columns[$column]) ? ($cells[$columns[$column]] ?? null) : null;

            try {
                $items[] = $this->item($value, $payingCompany, $isReimbursement);
            } catch (RowProblem $problem) {
                $errors[] = 'Row '.($index + 2).': '.$problem->getMessage();
            }
        }

        return $errors === [] ? ['items' => $items, 'errors' => []] : ['items' => [], 'errors' => $errors];
    }

    /**
     * @param  callable(string): mixed  $value
     * @return array<string, mixed>
     *
     * @throws RowProblem
     */
    protected function item(callable $value, ?Company $payingCompany, bool $isReimbursement): array
    {
        $details = $this->text($value('Item Details'));
        $invoiceNumber = $this->text($value('Invoice Number'));
        $glCode = $this->text($value('GL Number'));

        if ($details === '' || $invoiceNumber === '' || $glCode === '') {
            throw new RowProblem('Invoice Number, Item Details and GL Number are required.');
        }

        $invoiceDate = $this->date($value('Invoice Date'));
        $currency = Currency::query()->where('is_active', true)->where('code', Str::upper($this->text($value('Currency'))))->first()
            ?? throw new RowProblem("Currency \"{$this->text($value('Currency'))}\" was not found.");
        $amount = $this->amount($value('Amount'));

        $company = $this->company($this->text($value('Company')), $payingCompany);

        if ($company->isNot($payingCompany) && ! $isReimbursement) {
            throw new RowProblem("Company \"{$this->text($value('Company'))}\" is not the paying company ({$payingCompany?->short_name}). Leave Company blank or use the paying company.");
        }

        $costCenterCode = $this->optional($value('Cost Center'));
        $poNumber = $this->optional($value('PO Number'));
        $costCenter = null;

        if ($costCenterCode !== null) {
            $costCenter = CostCenter::query()->where('company_id', $payingCompany?->id)->where('code', $costCenterCode)->where('is_active', true)->first()
                ?? throw new RowProblem("Cost center \"{$costCenterCode}\" was not found under the paying company ({$payingCompany?->short_name}).");
            $poNumber = null; // charged to the cost center; a PO only applies without one
        } elseif ($poNumber === null) {
            throw new RowProblem('Enter a Cost Center or a PO Number.');
        }

        $subcategory = RfpForm::allowedSubcategories($costCenter?->id, $poNumber)
            ->filter(fn (Subcategory $subcategory): bool => $subcategory->glAccount->code === $glCode)
            ->sortByDesc('is_default')
            ->first();

        if ($subcategory === null) {
            throw new RowProblem(RfpForm::activeSubcategories()->contains(fn (Subcategory $subcategory): bool => $subcategory->glAccount->code === $glCode)
                ? ($costCenter
                    ? "GL Number {$glCode} cannot be charged to cost center {$costCenter->code}: ".($costCenter->isManufacturing() ? 'it is MFG and takes GL 5....' : 'it is OPEX and takes GL 6....')
                    : "GL Number {$glCode} does not take a PO Number; enter a Cost Center instead.")
                : "GL Number {$glCode} was not found. See the \"Sub Categories\" sheet.");
        }

        return [
            'description' => Str::limit($details, 150, ''),
            'invoice_number' => Str::limit($invoiceNumber, 50, ''),
            'invoice_date' => $invoiceDate,
            'cost_center_id' => $costCenter?->id,
            'po_number' => $poNumber === null ? null : Str::limit($poNumber, 15, ''),
            'rfp_category_id' => $subcategory->rfp_category_id,
            'rfp_subcategory_id' => $subcategory->id,
            'currency_id' => $currency->id,
            'amount' => $amount,
            'attachments' => [],
            ...$this->paidTo($this->text($value('Vendor Type')), $this->text($value('Vendor')), $company),
        ];
    }

    /**
     * Reimbursement "paid to" fields (ignored on a Standard RFP).
     *
     * @return array{company_id: int, vendor_type: string, vendor_id: ?int, rfp_one_time_vendor_id: ?int}
     *
     * @throws RowProblem
     */
    protected function paidTo(string $type, string $vendor, Company $company): array
    {
        $vendorType = match (Str::of($type)->lower()->replace(['-', '_'], ' ')->squish()->toString()) {
            '', 'vendor', 'existing vendor', '1' => RfpVendorType::Vendor,
            'one time vendor', 'onetime vendor', 'otv', '2' => RfpVendorType::OneTimeVendor,
            default => throw new RowProblem("Vendor Type \"{$type}\" is not valid. Use Vendor or One-time vendor."),
        };

        $fields = ['company_id' => $company->id, 'vendor_type' => $vendorType->value, 'vendor_id' => null, 'rfp_one_time_vendor_id' => null];

        if ($vendor === '') {
            return $fields; // chosen on screen
        }

        if ($vendorType === RfpVendorType::Vendor) {
            $fields['vendor_id'] = Vendor::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query->where('code', $vendor)->orWhereRaw('lower(name) = ?', [Str::lower($vendor)]))
                ->value('id') ?? throw new RowProblem("Vendor \"{$vendor}\" was not found. See the \"Vendors\" sheet.");
        } else {
            $fields['rfp_one_time_vendor_id'] = OneTimeVendor::query()
                ->where(fn ($query) => $query->where('tin', $vendor)->orWhereRaw('lower(name) = ?', [Str::lower($vendor)]))
                ->value('id') ?? throw new RowProblem("One-time vendor \"{$vendor}\" was not found. Add it on screen, or see the \"One-time Vendors\" sheet.");
        }

        return $fields;
    }

    /**
     * @throws RowProblem
     */
    protected function company(string $input, ?Company $payingCompany): Company
    {
        if ($input === '') {
            return $payingCompany ?? throw new RowProblem('Choose the paying company first.');
        }

        return Company::query()
            ->where(fn ($query) => $query->where('code', $input)->orWhereRaw('upper(short_name) = ?', [Str::upper($input)])->orWhereRaw('lower(name) = ?', [Str::lower($input)]))
            ->first() ?? throw new RowProblem("Company \"{$input}\" was not found. See the \"Companies\" sheet.");
    }

    /**
     * MM/DD/YYYY (or M/D/YY, as Excel exports to CSV), an Excel date, or YYYY-MM-DD.
     *
     * @throws RowProblem
     */
    protected function date(mixed $cell): string
    {
        if ($cell instanceof DateTimeInterface) {
            return CarbonImmutable::instance($cell)->toDateString();
        }

        // Excel date serial (days since 1899-12-30)
        if (is_numeric($cell) && $cell > 59) {
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) $cell)->toDateString();
        }

        $text = $this->text($cell);

        $withoutLeadingZeros = preg_replace('#(^|/)0(\d)#', '$1$2', $text); // 07/01/2026 → 7/1/2026

        foreach (['n/j/Y', 'n/j/y', 'Y-m-d'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat("!{$format}", $text);
            } catch (InvalidFormatException) {
                continue;
            }

            // Writing it back must give the input, so 31/07/2026 is not read as "month 31"
            if (in_array($date->format($format), [$text, $withoutLeadingZeros], true)) {
                return $date->toDateString();
            }
        }

        throw new RowProblem("Invoice Date \"{$text}\" is not a date. Use MM/DD/YYYY.");
    }

    /**
     * @throws RowProblem
     */
    protected function amount(mixed $cell): string
    {
        $clean = is_int($cell) || is_float($cell) ? (string) $cell : preg_replace('/[^0-9.\-]/', '', $this->text($cell));

        if (! is_numeric($clean) || (float) $clean <= 0) {
            throw new RowProblem("Amount \"{$this->text($cell)}\" must be a number greater than 0.");
        }

        return number_format((float) $clean, 2, '.', '');
    }

    /**
     * Blank and "N/A" mean "not given".
     */
    protected function optional(mixed $cell): ?string
    {
        $text = $this->text($cell);

        return in_array(Str::upper($text), ['', 'N/A', 'NA', '-'], true) ? null : $text;
    }

    /**
     * Cell as trimmed text; whole numbers lose the ".0" Excel adds (codes like 6006000).
     */
    protected function text(mixed $cell): string
    {
        if (is_float($cell) && floor($cell) === $cell) {
            return (string) (int) $cell;
        }

        if ($cell instanceof DateTimeInterface) {
            return $cell->format('m/d/Y');
        }

        return trim((string) $cell);
    }

    /**
     * @param  list<mixed>  $headerRow
     * @return array<string, int> template column => index in the file
     */
    protected function columnIndexes(array $headerRow): array
    {
        $wanted = collect(array_keys(self::COLUMNS))->mapWithKeys(fn (string $column): array => [Str::lower($column) => $column]);
        $aliases = ['vendor name' => 'Vendor', 'invoice no.' => 'Invoice Number', 'gl' => 'GL Number', 'po no.' => 'PO Number'];

        $indexes = [];

        foreach ($headerRow as $index => $cell) {
            $key = Str::lower($this->text($cell));
            $column = $wanted[$key] ?? $aliases[$key] ?? null;

            if ($column !== null && ! isset($indexes[$column])) {
                $indexes[$column] = $index;
            }
        }

        return $indexes;
    }

    /**
     * Rows of the "Items" sheet (or the first sheet), as plain arrays.
     *
     * @return list<list<mixed>>
     */
    protected function readRows(string $path, string $extension): array
    {
        $reader = $extension === 'csv' ? new CsvReader : new XlsxReader;
        $reader->open($path);

        $rows = null;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $isItems = $sheet->getName() === 'Items';

                // Use the "Items" sheet; fall back to the first sheet
                if ($rows !== null && ! $isItems) {
                    continue;
                }

                $rows = [];

                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();

                    if (count($rows) > self::MAX_ROWS + 1) {
                        break;
                    }
                }

                if ($isItems) {
                    break;
                }
            }
        } finally {
            $reader->close();
        }

        return $rows ?? [];
    }

    /**
     * @param  list<string>  $headers
     * @param  Collection<int, list<mixed>>  $rows
     * @param  list<float>  $widths
     */
    protected function addSheet(Writer $writer, string $name, array $headers, Collection $rows, Style $header, array $widths): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent()->setName($name);

        foreach ($widths as $index => $width) {
            $sheet->setColumnWidth($width, $index + 1);
        }

        $writer->addRow(Row::fromValues($headers, $header));
        $writer->addRows($rows->map(fn (array $values): Row => Row::fromValues($values))->all());
    }
}
