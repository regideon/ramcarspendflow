<?php

use App\Enums\RoleName;
use App\Filament\My\Pages\Approvals;
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

it('lists what is waiting for the approver, due soonest first', function () {
    $this->get(route('filament.my.pages.approvals'))
        ->assertOk()
        ->assertSee('3 waiting for you · ₱696,250.00 total')
        ->assertSeeInOrder(['Client visits – Cebu trip', 'Electricity – Pasig plant, October', 'Fiber internet – head office, Q4']);
});

it('sorts by highest amount', function () {
    Livewire::test(Approvals::class)
        ->call('setSort', 'amount')
        ->assertSeeInOrder(['Fiber internet', 'Electricity', 'Client visits']);
});

it('shows past decisions grouped by month', function () {
    Livewire::test(Approvals::class)
        ->call('setTab', 'done')
        ->assertSeeInOrder(['October 2026', 'Fleet fuel', 'September 2026', 'Office chairs replacement'])
        ->assertSee('Now in AP Processing')
        ->assertSee('Not in this year')
        ->assertDontSee('Electricity');
});

it('filters past decisions by approved, returned or rejected', function () {
    Livewire::test(Approvals::class)
        ->call('setTab', 'done')
        ->assertSee('Approved')
        ->call('setDecision', 'returned')
        ->assertSee('Marketing collaterals')
        ->assertDontSee('Fleet fuel')
        ->assertDontSee('Office chairs replacement')
        ->call('setDecision', 'rejected')
        ->assertSee('Office chairs replacement')
        ->assertDontSee('Marketing collaterals');
});

it('shows the search bar on phones without a tap', function () {
    $this->get(route('filament.my.pages.approvals'))
        ->assertOk()
        ->assertSee('Search payee, purpose, requestor or RFP #')
        ->assertDontSee('searchOpen');
});

it('filters what is waiting by due date, type, department and amount', function () {
    Livewire::test(Approvals::class)
        ->set('filters.due', 'overdue')
        ->assertSee('Client visits')
        ->assertDontSee('Electricity')
        ->set('filters.due', '')
        ->set('filters.type', ['reimbursement'])
        ->assertSee('Client visits')
        ->assertDontSee('Fiber internet')
        ->call('resetFilters')
        ->set('filters.department', ['IT Infra'])
        ->assertSee('Fiber internet')
        ->assertDontSee('Client visits')
        ->call('resetFilters')
        ->set('filters.min', '100,000')
        ->set('filters.max', '300000')
        ->assertSee('Electricity')
        ->assertDontSee('Fiber internet')
        ->assertDontSee('Client visits')
        ->assertSee('Show 1 result');
});

it('filters past decisions by when they were made', function () {
    $this->travelTo('2026-10-29');

    Livewire::test(Approvals::class)
        ->call('setTab', 'done')
        ->set('filters.decided', 'month')
        ->assertSee('Fleet fuel')
        ->assertDontSee('Office chairs replacement');
});

it('says when nothing matches the filters', function () {
    Livewire::test(Approvals::class)
        ->set('filters.min', '1,000,000')
        ->assertSee('Nothing here matches your filters.')
        ->assertSee('Clear filters');
});

it('searches by payee, purpose, requestor or RFP number', function () {
    Livewire::test(Approvals::class)
        ->set('search', 'aileen')
        ->assertSee('Fiber internet')
        ->assertDontSee('Client visits');
});

it('shows an empty state when nothing matches', function () {
    Livewire::test(Approvals::class)
        ->set('search', 'no such request')
        ->assertSee('No matches');
});

it('opens the approve sheet when linked from an Approve button', function () {
    $this->get(route('filament.my.pages.rfps.{number}', ['number' => 'RFP-2026-001482', 'decide' => 'approve']))
        ->assertOk()
        ->assertSee("sheet: 'approve'", escape: false);
});

it('opens a past decision read-only', function () {
    $this->get(route('filament.my.pages.rfps.{number}', ['number' => 'RFP-2026-001449']))
        ->assertOk()
        ->assertSee('Marketing collaterals – Q4 launch')
        ->assertDontSee('Confirm approval')
        ->assertDontSee('Edit &amp; resubmit', escape: false);
});

it('is only for matrix approvers', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('filament.my.pages.approvals'))
        ->assertForbidden();
});
