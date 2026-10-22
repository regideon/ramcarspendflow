<?php

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Enums\TaxCodeCategory;
use App\Filament\Resources\P2p\Approvals\Pages\ListApprovals;
use App\Filament\Resources\P2p\Invoices\Pages\ListInvoices;
use App\Livewire\P2p\InvoiceRecordPane;
use App\Models\P2p\Invoice;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WithholdingTaxCode;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndUserSeeder::class);
});

function splitViewVendor(): User
{
    $vendor = Vendor::factory()->create();
    $user = User::factory()->create(['vendor_id' => $vendor->id]);
    $user->assignRole(RoleName::Vendor->value);

    return $user;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function splitViewInvoice(User $user, array $attributes = []): Invoice
{
    return Invoice::factory()->create([
        'user_id' => $user->id,
        'vendor_id' => $user->vendor_id,
        'status' => P2pStatus::SubmittedToAp,
        ...$attributes,
    ]);
}

it('hydrates the selected request from the record query parameter', function () {
    $vendor = splitViewVendor();
    $this->actingAs($vendor);

    $invoice = splitViewInvoice($vendor, ['status' => P2pStatus::ReturnedToVendor]);

    Livewire::withQueryParams(['record' => $invoice->id])
        ->test(ListInvoices::class)
        ->assertSet('selectedRecordId', $invoice->id)
        ->assertSee($invoice->grc_number);
});

it('opens the pane from a row click instead of navigating', function () {
    $processor = User::factory()->create();
    $processor->assignRole(RoleName::ApProcessor->value);
    $this->actingAs($processor);

    $invoice = Invoice::factory()->create(['status' => P2pStatus::SubmittedToAp]);

    $component = Livewire::test(ListInvoices::class);
    $table = $component->instance()->getTable();

    expect($table->getRecordAction($invoice))->toBe('view')
        ->and($table->getRecordUrl($invoice))->toBeNull();

    // The page itself is renderless; the pane component does the reload.
    $component->call('selectRecord', $invoice->id)
        ->assertSet('selectedRecordId', $invoice->id)
        ->assertDispatched('p2p-record-selected', id: $invoice->id);
});

it('refuses to select another vendor request', function () {
    $vendor = splitViewVendor();
    $this->actingAs($vendor);

    $other = splitViewInvoice(User::factory()->create());

    Livewire::test(InvoiceRecordPane::class, ['invoiceId' => $other->id])
        ->assertDontSee($other->grc_number);
});

it('lets a vendor correct the invoice date and references in the pane', function () {
    $vendor = splitViewVendor();
    $this->actingAs($vendor);

    $invoice = splitViewInvoice($vendor, [
        'status' => P2pStatus::ReturnedToVendor,
        'invoice_at' => now()->subDays(3),
        'reference_number' => ['OLD-REF'],
    ]);

    Livewire::test(InvoiceRecordPane::class, ['invoiceId' => $invoice->id])
        ->call('startEditing')
        ->assertSet('isEditing', true)
        ->set('recordData.invoice_at', '2026-10-15')
        ->set('recordData.reference_number', 'REF-1, REF-2')
        ->call('save')
        ->assertSet('isEditing', false);

    $fresh = $invoice->fresh();

    expect($fresh->invoice_at?->toDateString())->toBe('2026-10-15')
        ->and($fresh->reference_number)->toBe(['REF-1', 'REF-2']);
});

it('lets an AP processor set the tax codes and recalculates the summary', function () {
    $processor = User::factory()->create();
    $processor->assignRole(RoleName::ApProcessor->value);
    $this->actingAs($processor);

    TaxCode::factory()->create(['code' => 'I2', 'category' => TaxCodeCategory::Taxable, 'rate' => 0.120]);
    WithholdingTaxCode::factory()->create(['code' => '04', 'rate' => 2.00]);

    $invoice = Invoice::factory()->create([
        'status' => P2pStatus::SubmittedToAp,
        'amount_submitted' => 1000.00,
        'tax_code' => null,
        'withholding_tax_code' => null,
    ]);

    Livewire::test(InvoiceRecordPane::class, ['invoiceId' => $invoice->id])
        ->call('startEditing')
        ->assertSet('isEditing', true)
        ->set('recordData.tax_code', 'I2')
        ->set('recordData.withholding_tax_code', '04')
        ->set('recordData.withholding_tax_base', '1000')
        ->call('save');

    $fresh = $invoice->fresh();

    expect($fresh->tax_code)->toBe('I2')
        ->and($fresh->withholding_tax_code)->toBe('04')
        ->and($fresh->total)->toBe('1120.00')
        ->and($fresh->ewt)->toBe('20.00')
        ->and($fresh->net)->toBe('1100.00');
});

it('does not let the AP supervisor edit request fields', function () {
    $supervisor = User::factory()->create();
    $supervisor->assignRole(RoleName::ApSupervisor->value);
    $this->actingAs($supervisor);

    $invoice = Invoice::factory()->create(['status' => P2pStatus::SubmittedToChecker]);

    Livewire::test(InvoiceRecordPane::class, ['invoiceId' => $invoice->id])
        ->call('startEditing')
        ->assertSet('isEditing', false);
});

it('opens an approvals row in the pane without leaving the queue', function () {
    $processor = User::factory()->create();
    $processor->assignRole(RoleName::ApProcessor->value);
    $this->actingAs($processor);

    $invoice = Invoice::factory()->create([
        'status' => P2pStatus::SubmittedToAp,
        'submitted_at' => now()->subDay(),
    ]);

    Livewire::test(ListApprovals::class)
        ->call('selectRecord', $invoice->id)
        ->assertSet('selectedRecordId', $invoice->id)
        ->assertDispatched('p2p-record-selected', id: $invoice->id);

    Livewire::test(InvoiceRecordPane::class, ['invoiceId' => $invoice->id])
        ->assertSee($invoice->grc_number)
        ->assertSee('Open review screen');
});
