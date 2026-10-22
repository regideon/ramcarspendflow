<?php

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Pages\P2p\BulkGoodsReceipt;
use App\Filament\Resources\P2p\Invoices\Pages\ListInvoices;
use App\Filament\Resources\P2p\PurchaseOrders\PurchaseOrderResource;
use App\Livewire\P2p\InvoiceRecordPane;
use App\Models\Company;
use App\Models\P2p\Invoice;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

pest()->use(RefreshDatabase::class);

function invoiceListVendor(): User
{
    $vendor = Vendor::factory()->create();
    $user = User::factory()->create(['vendor_id' => $vendor->id]);
    $user->assignRole(Role::findOrCreate(RoleName::Vendor->value, 'web'));

    return $user;
}

function invoiceListProcessor(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate(RoleName::ApProcessor->value, 'web'));

    return $user;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function invoiceListInvoice(User $user, array $attributes = []): Invoice
{
    return Invoice::factory()->create([
        'user_id' => $user->id,
        'status' => P2pStatus::SubmittedToAp,
        ...$attributes,
    ]);
}

it('defaults to the All tab and drops the redundant Pending tab', function () {
    $this->actingAs(invoiceListVendor());

    $component = Livewire::test(ListInvoices::class)->instance();

    expect($component->getDefaultActiveTab())->toBe('all')
        ->and($component->getCachedTabs())->toHaveKeys(['in_progress', 'returned', 'cancelled', 'all'])
        ->and($component->getCachedTabs())->not->toHaveKey('pending');
});

it('shows the company name for an invoice whose company has no short name', function () {
    $user = invoiceListVendor();
    $this->actingAs($user);

    $company = Company::factory()->create(['name' => 'Ramcar Inc', 'short_name' => null]);
    $invoice = invoiceListInvoice($user, ['company_id' => $company->id]);

    Livewire::test(ListInvoices::class)
        ->assertTableColumnStateSet('company.name', 'Ramcar Inc', $invoice);
});

it('opens a submission in the split view and keeps the process-screen link for vendors', function () {
    $user = invoiceListVendor();
    $this->actingAs($user);

    $invoice = invoiceListInvoice($user);
    $url = BulkGoodsReceipt::getUrl(['invoice' => $invoice->id]);

    $component = Livewire::test(ListInvoices::class);

    $component->assertTableActionVisible('view', $invoice);
    expect($component->instance()->getTable()->getRecordAction($invoice))->toBe('view')
        ->and($component->instance()->getTable()->getRecordUrl($invoice))->toBeNull();

    Livewire::test(InvoiceRecordPane::class, ['invoiceId' => $invoice->id])
        ->assertSeeHtml('href="'.$url.'"');

    $component->call('selectRecord', $invoice->id)
        ->assertSet('selectedRecordId', $invoice->id)
        ->assertDispatched('p2p-record-selected', id: $invoice->id);
});

it('opens AP processor rows in the split view', function () {
    $this->actingAs(invoiceListProcessor());

    $invoice = invoiceListInvoice(User::factory()->create());

    $component = Livewire::test(ListInvoices::class);

    $component->assertTableActionVisible('view', $invoice);
    expect($component->instance()->getTable()->getRecordAction($invoice))->toBe('view')
        ->and($component->instance()->getTable()->getRecordUrl($invoice))->toBeNull();

    $component->call('selectRecord', $invoice->id)
        ->assertSet('selectedRecordId', $invoice->id)
        ->assertDispatched('p2p-record-selected', id: $invoice->id);
});

it('links the Process header action to purchase orders for vendors', function () {
    $this->actingAs(invoiceListVendor());

    Livewire::test(ListInvoices::class)
        ->assertActionVisible('process')
        ->assertActionHasUrl('process', PurchaseOrderResource::getUrl('index'));
});

it('hides the Process header action from AP processors', function () {
    $this->actingAs(invoiceListProcessor());

    Livewire::test(ListInvoices::class)
        ->assertActionHidden('process');
});
