<?php

use App\Enums\RoleName;
use App\Filament\Exports\P2pInvoiceExporter;
use App\Filament\Resources\P2p\Invoices\Pages\ListInvoices;
use App\Models\User;
use Database\Seeders\RoleAndUserSeeder;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndUserSeeder::class);
});

/**
 * @return array<int, string>
 */
function invoiceExportLabels(): array
{
    return collect(P2pInvoiceExporter::getColumns())
        ->map(fn ($column): string => (string) $column->getLabel())
        ->all();
}

it('hides the invoice export from staff for now', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor));

    Livewire::test(ListInvoices::class)->assertActionDoesNotExist('export');
});

it('hides the invoice export from vendors for now', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::Vendor));

    Livewire::test(ListInvoices::class)->assertActionDoesNotExist('export');
});

it('exports the full column set to staff', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor));

    $labels = invoiceExportLabels();

    // Staff see the vendor, not the vendor's own company view.
    expect($labels)->toContain('GRC No.', 'Vendor', 'PO', 'Reference Number', 'Invoice Date', 'GR', 'Amount', 'Invoice No', 'Status')
        ->and($labels)->not->toContain('Company');
});

it('withholds the amount and vendor from a vendor export', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::Vendor));

    $labels = invoiceExportLabels();

    // The vendor gets their own company instead of the vendor/amount columns.
    expect($labels)->toContain('Company', 'PO', 'GR', 'Invoice No')
        ->and($labels)->not->toContain('Vendor')
        ->and($labels)->not->toContain('Amount');
});

it('names the exported row count in the completion notification', function () {
    $export = new Export(['successful_rows' => 3]);

    expect(P2pInvoiceExporter::getCompletedNotificationBody($export))
        ->toContain('3 rows');
});
