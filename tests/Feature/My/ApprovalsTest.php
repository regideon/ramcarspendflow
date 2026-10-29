<?php

use App\Enums\RoleName;
use App\Filament\My\Pages\Approvals;
use App\Models\CompanyRole;
use App\Models\Rfp\Setting;
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
        ->assertSee('8 waiting for you · ₱1,253,130.50 total')
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
        ->assertSee('Show 4 results');
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

it('lets RFPs under 200K be ticked for mass approval and locks the high amounts', function () {
    $this->get(route('filament.my.pages.approvals'))
        ->assertOk()
        ->assertSee('value="RFP-2026-001490"', escape: false)
        ->assertSee('value="RFP-2026-001493"', escape: false)
        ->assertDontSee('value="RFP-2026-001482"', escape: false)
        ->assertDontSee('value="RFP-2026-001475"', escape: false)
        ->assertSee('High amount · approve one by one')
        ->assertSee('Select all under ₱200,000.00');

    expect(Livewire::test(Approvals::class)->instance()->bulkAmounts())
        ->toBe([
            'RFP-2026-001490' => 38450.00,
            'RFP-2026-001493' => 18500.00,
            'RFP-2026-001496' => 12730.50,
            'RFP-2026-001498' => 185000.00,
            'RFP-2026-001499' => 176400.00,
            'RFP-2026-001502' => 164250.00,
        ]);
});

it('mass approves the ticked RFPs and drops them from the queue', function () {
    Livewire::test(Approvals::class)
        ->call('approveSelected', ['RFP-2026-001490', 'RFP-2026-001493'])
        ->assertNotified('2 RFPs approved')
        ->assertSet('approved', ['RFP-2026-001490', 'RFP-2026-001493'])
        ->assertDontSee('Client visits – Cebu trip')
        ->assertDontSee('Aircon cleaning – 2nd floor')
        ->assertSee('Printer toner and bond paper')
        ->assertSee('6 waiting for you · ₱1,196,180.50 total');
});

it('refuses to mass approve an RFP of 200K and above', function () {
    Livewire::test(Approvals::class)
        ->call('approveSelected', ['RFP-2026-001490', 'RFP-2026-001482'])
        ->assertNotified('Those RFPs can’t be approved together')
        ->assertSet('approved', [])
        ->assertSee('Client visits – Cebu trip');
});

it('refuses to mass approve RFPs not waiting on the approver', function () {
    Livewire::test(Approvals::class)
        ->call('approveSelected', ['RFP-2026-999999'])
        ->assertNotified('Those RFPs can’t be approved together')
        ->assertSet('approved', []);
});

it('refuses a mass approval that adds up to more than the batch total limit', function () {
    // 185,000 + 176,400 + 164,250 = 525,650, over the default 500,000
    Livewire::test(Approvals::class)
        ->assertSee('up to ₱500,000.00 per batch')
        ->call('approveSelected', ['RFP-2026-001498', 'RFP-2026-001499', 'RFP-2026-001502'])
        ->assertNotified('Over the bulk approval limit')
        ->assertSet('approved', [])
        ->call('approveSelected', ['RFP-2026-001498', 'RFP-2026-001499'])
        ->assertNotified('2 RFPs approved');
});

it('takes the mass approval limits from RFP settings', function () {
    Setting::current()->update(['bulk_approval_item_limit' => 40000, 'bulk_approval_total_limit' => 50000]);

    expect(array_keys(Livewire::test(Approvals::class)->instance()->bulkAmounts()))
        ->toBe(['RFP-2026-001490', 'RFP-2026-001493', 'RFP-2026-001496']);

    Livewire::test(Approvals::class)
        ->assertSee('Select all under ₱40,000.00')
        ->assertSee('up to ₱50,000.00 per batch')
        ->call('approveSelected', ['RFP-2026-001498'])
        ->assertNotified('Those RFPs can’t be approved together')
        ->call('approveSelected', ['RFP-2026-001490', 'RFP-2026-001493'])
        ->assertNotified('Over the bulk approval limit')
        ->call('approveSelected', ['RFP-2026-001493', 'RFP-2026-001496'])
        ->assertNotified('2 RFPs approved');
});
