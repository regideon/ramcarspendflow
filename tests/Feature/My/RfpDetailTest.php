<?php

use App\Enums\RoleName;
use App\Filament\My\Pages\RfpDetail;
use App\Models\CompanyRole;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    CompanyRole::factory()->for($this->user)->create(['role' => RoleName::DepartmentHead]);

    $this->actingAs($this->user);
    Filament::setCurrentPanel('my');
});

it('shows the requestor their own RFP without decision buttons', function () {
    $this->get(route('filament.my.pages.rfps.{number}', ['number' => 'RFP-2026-001399']))
        ->assertOk()
        ->assertSeeInOrder(['Office supplies – October', 'Where it is now', 'Items (3)', 'Attachments (3)'])
        ->assertSee('Waiting for Department Head approval')
        ->assertDontSee('Confirm approval');
});

it('gives the approver whose turn it is the decision buttons', function () {
    $this->get(route('filament.my.pages.rfps.{number}', ['number' => 'RFP-2026-001482']))
        ->assertOk()
        ->assertSee('Electricity – Pasig plant, October')
        ->assertSee('Confirm approval');
});

it('shows the return note to the requestor of a returned RFP', function () {
    $this->get(route('filament.my.pages.rfps.{number}', ['number' => 'RFP-2026-001382']))
        ->assertOk()
        ->assertSee('Edit &amp; resubmit', escape: false)
        ->assertSee('Please attach the signed quotation.');
});

it('returns 404 for an unknown RFP', function () {
    $this->get(route('filament.my.pages.rfps.{number}', ['number' => 'RFP-2026-999999']))
        ->assertNotFound();
});

it('approves without remarks', function () {
    Livewire::test(RfpDetail::class, ['number' => 'RFP-2026-001482'])
        ->call('decide', 'approve')
        ->assertHasNoErrors()
        ->assertReturned(true)
        ->assertNotified('Approved');
});

it('needs remarks to return or reject', function (string $decision) {
    Livewire::test(RfpDetail::class, ['number' => 'RFP-2026-001482'])
        ->call('decide', $decision, '')
        ->assertHasErrors(['remarks' => 'required_unless']);
})->with(['return', 'reject']);

it('does not let the requestor decide on their own RFP', function () {
    Livewire::test(RfpDetail::class, ['number' => 'RFP-2026-001399'])
        ->call('decide', 'approve')
        ->assertForbidden();
});
