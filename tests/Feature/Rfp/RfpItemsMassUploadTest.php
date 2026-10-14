<?php

use App\Enums\RfpVendorType;
use App\Filament\Resources\Rfps\Pages\CreateRfp;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\Rfp\OneTimeVendor;
use App\Models\Rfp\Subcategory;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Rfp\RfpItemsSpreadsheet;
use Database\Seeders\AccountGroupSeeder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\DemoMasterDataSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\Rfp\DueTypeSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([CompanySeeder::class, CurrencySeeder::class, AccountGroupSeeder::class, PaymentMethodSeeder::class, DemoMasterDataSeeder::class, RoleAndUserSeeder::class, DueTypeSeeder::class]);

    $this->company = Company::firstWhere('code', '1000');
    $this->finance = CostCenter::query()->where('company_id', $this->company->id)->where('description', 'Finance & Accounting')->first(); // OPEX
    $this->plant = CostCenter::query()->where('company_id', $this->company->id)->where('description', 'Plant Operations')->first(); // MFG
    $this->sheet = app(RfpItemsSpreadsheet::class);
});

/**
 * @param  list<list<string|int|float>>  $rows
 */
function itemsCsv(array $rows, ?array $header = null): string
{
    $path = tempnam(sys_get_temp_dir(), 'items').'.csv';
    $handle = fopen($path, 'w');
    fputcsv($handle, $header ?? array_keys(RfpItemsSpreadsheet::COLUMNS));

    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }

    fclose($handle);

    return $path;
}

it('reads a filled-in sheet into items, with GL Number as the Sub Category', function () {
    $path = itemsCsv([
        ['12629', '7/31/26', 'Office supplies', '6021010', $this->finance->code, 'N/A', '', '', '', 'PHP', '2,500'],
        ['N/A', '07/01/2026', 'Down payment', '1151010', 'N/A', '4500001234', 'PBI', '', '', 'usd', 100],
    ]);

    $result = $this->sheet->parse($path, 'csv', $this->company->id, false);
    $officeSupplies = Subcategory::firstWhere('name', 'Office Supplies');

    expect($result['errors'])->toBe([])
        ->and($result['items'])->toHaveCount(2)
        ->and($result['items'][0])->toMatchArray([
            'invoice_number' => '12629',
            'invoice_date' => '2026-07-31',
            'description' => 'Office supplies',
            'cost_center_id' => $this->finance->id,
            'po_number' => null,
            'rfp_category_id' => $officeSupplies->rfp_category_id,
            'rfp_subcategory_id' => $officeSupplies->id,
            'currency_id' => Currency::firstWhere('code', 'PHP')->id,
            'amount' => '2500.00',
        ])
        ->and($result['items'][1])->toMatchArray([
            'invoice_number' => 'N/A',
            'cost_center_id' => null,
            'po_number' => '4500001234',
            'rfp_subcategory_id' => Subcategory::firstWhere('name', 'Supplier Down Payment')->id,
            'currency_id' => Currency::firstWhere('code', 'USD')->id,
        ]);
});

it('matches columns by header name, so the legacy column order still works', function () {
    $path = itemsCsv(
        [['PHP', 150, 'INV-9', '07/15/2026', 'Bond paper', '6021010', $this->finance->code]],
        ['Currency', 'Amount', 'Invoice Number', 'Invoice Date', 'Item Details', 'GL Number', 'Cost Center'],
    );

    expect($this->sheet->parse($path, 'csv', $this->company->id, false))
        ->errors->toBe([])
        ->items->toHaveCount(1);
});

it('applies the charging-first rules and lists every problem by row', function () {
    $path = itemsCsv([
        ['1', '07/01/2026', 'MFG G/L on an OPEX cost center', '5021010', $this->finance->code, '', '', '', '', 'PHP', 10],
        ['2', '07/01/2026', 'No cost center or PO', '6021010', 'N/A', 'N/A', '', '', '', 'PHP', 10],
        ['3', '07/01/2026', 'Unknown GL', '9999999', $this->finance->code, '', '', '', '', 'PHP', 10],
        ['4', '31/07/2026', 'Bad date', '6021010', $this->finance->code, '', '', '', '', 'PHP', 10],
        ['5', '07/01/2026', 'Bad amount', '6021010', $this->finance->code, '', '', '', '', 'PHP', 0],
        ['6', '07/01/2026', 'Other company on a Standard RFP', '6021010', $this->finance->code, '', '2000', '', '', 'PHP', 10],
        ['7', '07/01/2026', 'Fine', '5021010', $this->plant->code, '', '', '', '', 'PHP', 10],
    ]);

    $result = $this->sheet->parse($path, 'csv', $this->company->id, false);

    expect($result['items'])->toBe([])
        ->and($result['errors'])->toHaveCount(6)
        ->and($result['errors'][0])->toStartWith('Row 2: GL Number 5021010 cannot be charged to cost center')
        ->and($result['errors'][1])->toBe('Row 3: Enter a Cost Center or a PO Number.')
        ->and($result['errors'][2])->toContain('GL Number 9999999 was not found')
        ->and($result['errors'][3])->toContain('Invoice Date "31/07/2026" is not a date')
        ->and($result['errors'][4])->toContain('must be a number greater than 0')
        ->and($result['errors'][5])->toContain('is not the paying company');
});

it('reads who was paid on a Reimbursement', function () {
    $vendor = Vendor::firstWhere('code', '200001');
    $oneTime = OneTimeVendor::query()->first();

    $result = $this->sheet->parse(itemsCsv([
        ['1', '07/01/2026', 'Supplies', '6021010', $this->finance->code, '', 'OMMC', 'Vendor', $vendor->name, 'PHP', 10],
        ['2', '07/01/2026', 'Supplies', '6021010', $this->finance->code, '', '', 'OTV', $oneTime->tin, 'PHP', 10],
        ['3', '07/01/2026', 'Supplies', '6021010', $this->finance->code, '', '', 'Vendor', 'Nobody Inc.', 'PHP', 10],
    ]), 'csv', $this->company->id, true);

    expect($result['errors'])->toBe(['Row 4: Vendor "Nobody Inc." was not found. See the "Vendors" sheet.']);

    $result = $this->sheet->parse(itemsCsv([
        ['1', '07/01/2026', 'Supplies', '6021010', $this->finance->code, '', 'OMMC', 'Vendor', $vendor->name, 'PHP', 10],
        ['2', '07/01/2026', 'Supplies', '6021010', $this->finance->code, '', '', 'OTV', $oneTime->tin, 'PHP', 10],
    ]), 'csv', $this->company->id, true);

    expect($result['items'][0])->toMatchArray(['company_id' => Company::firstWhere('code', '2000')->id, 'vendor_type' => RfpVendorType::Vendor->value, 'vendor_id' => $vendor->id])
        ->and($result['items'][1])->toMatchArray(['company_id' => $this->company->id, 'vendor_type' => RfpVendorType::OneTimeVendor->value, 'rfp_one_time_vendor_id' => $oneTime->id]);
});

it('builds a template with the Items sheet first and master data sheets', function () {
    $reader = new Reader;
    $reader->open($this->sheet->template());

    $sheets = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $sheets[$sheet->getName()] = $row->toArray();

            break;
        }
    }

    $reader->close();

    expect(array_keys($sheets))->toBe(['Items', 'Instructions', 'Companies', 'Sub Categories', 'Cost Centers', 'Vendor Types', 'Vendors', 'One-time Vendors', 'Currencies'])
        ->and($sheets['Items'])->toBe(array_keys(RfpItemsSpreadsheet::COLUMNS))
        ->and($sheets['Items'])->toContain('Vendor Type', 'Vendor', 'GL Number');
});

it('fills the Items repeater from an uploaded file and downloads the template', function () {
    $this->actingAs(User::firstWhere('email', 'requestor@ramcar.com'));
    $undo = Repeater::fake();

    $file = UploadedFile::fake()->createWithContent('items.csv', file_get_contents(itemsCsv([
        ['A-1', '07/01/2026', 'Bond paper', '6021010', $this->finance->code, '', '', '', '', 'PHP', 120],
        ['A-2', '07/02/2026', 'Toner', '6021010', $this->finance->code, '', '', '', '', 'PHP', 80],
    ])));

    $component = Livewire::test(CreateRfp::class)
        ->fillForm(['company_id' => $this->company->id])
        ->callAction(TestAction::make('uploadItems')->schemaComponent('massUploadActions'), data: ['file' => $file])
        ->assertHasNoActionErrors()
        ->assertNotified('2 items added from the file');

    expect(collect($component->get('data.items'))->pluck('description')->values()->all())->toBe(['Bond paper', 'Toner']);

    $component->callAction(TestAction::make('downloadTemplate')->schemaComponent('massUploadActions'))
        ->assertFileDownloaded('RFP items template.xlsx');

    $undo();
});

it('allows any G/L of the cost center expense type, even if not in the cost center mapping', function () {
    expect($this->finance->glAccounts()->where('code', '6101130')->exists())->toBeFalse(); // Digital Advertising: not mapped to Finance

    $result = $this->sheet->parse(itemsCsv([
        ['1', '07/01/2026', 'Ads', '6101130', $this->finance->code, '', '', '', '', 'PHP', 10],
    ]), 'csv', $this->company->id, false);

    expect($result['errors'])->toBe([])
        ->and($result['items'][0]['rfp_subcategory_id'])->toBe(Subcategory::firstWhere('name', 'Digital Advertising')->id);
});
