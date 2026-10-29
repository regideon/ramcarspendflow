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

it('approves without remarks and shows where the RFP goes next', function () {
    Livewire::test(RfpDetail::class, ['number' => 'RFP-2026-001490'])
        ->assertDontSee('RFP approved')
        ->call('submitDecision', 'approve')
        ->assertHasNoErrors()
        ->assertReturned(true)
        ->assertSee('RFP approved')
        ->assertSee("It's now with the BU Head. We'll let Juan know.")
        ->assertSee('Step 3 of 4 signed')
        ->assertSee('Review next · Meralco')
        ->assertSee('Preview only. Nothing was saved yet.');
});

it('shows a returned screen with the remarks', function () {
    Livewire::test(RfpDetail::class, ['number' => 'RFP-2026-001490'])
        ->call('submitDecision', 'return', 'Please attach the hotel receipts.')
        ->assertSee('Returned to Juan')
        ->assertSee('Please attach the hotel receipts.')
        ->assertDontSee('signed');
});

it('needs remarks to return or reject', function (string $decision) {
    Livewire::test(RfpDetail::class, ['number' => 'RFP-2026-001482'])
        ->call('submitDecision', $decision, '')
        ->assertHasErrors(['remarks' => 'required_unless']);
})->with(['return', 'reject']);

it('does not let the requestor decide on their own RFP', function () {
    Livewire::test(RfpDetail::class, ['number' => 'RFP-2026-001399'])
        ->call('submitDecision', 'approve')
        ->assertForbidden();
});
