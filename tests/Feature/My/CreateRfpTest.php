<?php

use App\Filament\My\Pages\CreateRfp;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('my');
});

function fillValidRfp(Testable $page): Testable
{
    return $page
        ->set('form.vendor', 'National Book Store')
        ->set('form.purpose', 'Office supplies – October')
        ->set('items.0.upload', UploadedFile::fake()->create('Official_Receipt.pdf', 120, 'application/pdf'))
        ->set('items.0.description', 'A4 copy paper (20 reams)')
        ->set('items.0.costCenter', '101100')
        ->set('items.0.category', 'Supplies')
        ->set('items.0.subcategory', 'OPEX - Office Supplies')
        ->set('items.0.invoiceNo', 'NBS-88910')
        ->set('items.0.invoiceDate', today()->subDay()->toDateString())
        ->set('items.0.amount', '4,850.00');
}

it('opens the new RFP form', function () {
    $this->get(route('filament.my.pages.requests.new'))
        ->assertOk()
        ->assertSeeInOrder(['Request', 'Payee', 'Purpose', 'Items (1)', 'Approval route']);
});

it('preselects reimbursement from the link', function () {
    $this->get(route('filament.my.pages.requests.new', ['type' => 'reimbursement']))
        ->assertOk()
        ->assertSee('Paid back to you');
});

it('checks the request details before the items', function () {
    Livewire::test(CreateRfp::class)
        ->call('validateDetails')
        ->assertHasErrors(['form.vendor' => 'required', 'form.purpose' => 'required']);
});

it('does not ask for a vendor on a reimbursement', function () {
    Livewire::test(CreateRfp::class)
        ->set('form.requestType', 'reimbursement')
        ->set('form.purpose', 'Client visits – Cebu trip')
        ->call('validateDetails')
        ->assertHasNoErrors();
});

it('needs the proof of approval when pre-approved', function () {
    Livewire::test(CreateRfp::class)
        ->set('form.vendor', 'National Book Store')
        ->set('form.purpose', 'Office supplies')
        ->set('form.preApproved', true)
        ->call('validateDetails')
        ->assertHasErrors(['proof' => 'required']);
});

it('points to the item that needs fixing', function () {
    Livewire::test(CreateRfp::class)
        ->set('form.vendor', 'National Book Store')
        ->set('form.purpose', 'Office supplies')
        ->call('submit')
        ->assertReturned(['ok' => false, 'step' => 2, 'item' => 0])
        ->assertHasErrors(['items.0.upload', 'items.0.amount']);
});

it('adds and removes items', function () {
    Livewire::test(CreateRfp::class)
        ->call('addItem')
        ->assertCount('items', 2)
        ->call('removeItem', 0)
        ->assertCount('items', 1)
        ->call('removeItem', 0)
        ->assertCount('items', 1);
});

it('submits a valid RFP as a preview', function () {
    fillValidRfp(Livewire::test(CreateRfp::class))
        ->call('submit')
        ->assertHasNoErrors()
        ->assertReturned(['ok' => true, 'step' => 2, 'item' => null])
        ->assertSet('submitted.amount', 4850.0)
        ->assertSee('RFP submitted');
});
