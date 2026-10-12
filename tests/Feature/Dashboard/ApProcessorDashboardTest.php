<?php

use App\Enums\RoleName;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\P2p\Approvals\ApprovalResource;
use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Filament\Widgets\Accounting\ApProcessor;
use App\Models\User;
use App\Services\Accounting\ApProcessorDashboard;
use Database\Seeders\CompanySeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([CompanySeeder::class, RoleAndUserSeeder::class]);

    $this->processor = User::firstWhere('email', 'ap.processor@ramcar.com');
});

it('shows the AP Processor their work queue dashboard', function () {
    $this->actingAs($this->processor)
        ->get(Dashboard::getUrl())
        ->assertOk()
        ->assertSee('18 items in your queue · 4 overdue.');

    Livewire::test(Dashboard::class)
        ->assertActionVisible('openRfpQueue')
        ->assertActionHasUrl('openRfpQueue', RfpApprovalResource::getUrl('index'))
        ->assertActionVisible('processP2pInvoices')
        ->assertActionHasUrl('processP2pInvoices', ApprovalResource::getUrl('index'))
        ->assertActionHidden('syncFromSap')
        ->assertActionHidden('newStandard');

    expect((new Dashboard)->getWidgets())->toContain(ApProcessor\WorkQueue::class);
});

it('renders every AP Processor widget', function (string $widget) {
    $this->actingAs($this->processor);

    Livewire::test($widget)->assertOk();
})->with([
    ApProcessor\BackOnDesk::class,
    ApProcessor\ApProcessorStats::class,
    ApProcessor\WorkQueue::class,
    ApProcessor\QueueAging::class,
    ApProcessor\KeepingUp::class,
    ApProcessor\EscalationsRaised::class,
    ApProcessor\TaxSummary::class,
    ApProcessor\Insights::class,
]);

it('puts every queue row in known tabs and flags past-due rows as overdue', function () {
    foreach ((new ApProcessorDashboard($this->processor))->queue() as $row) {
        expect(array_diff($row['tabs'], array_keys(ApProcessorDashboard::TABS)))->toBeEmpty()
            ->and(in_array('overdue', $row['tabs'], true))->toBe($row['due_date']->lt(today()));
    }
});

it('prefers the AP dashboard for an AP Processor who also raises RFPs', function () {
    $this->processor->assignRole(RoleName::Requestor->value);
    $this->actingAs($this->processor);

    expect((new Dashboard)->getWidgets())->toContain(ApProcessor\WorkQueue::class);
});

it('keeps the AP Processor widgets away from other roles', function () {
    $this->actingAs(User::firstWhere('email', 'ap.supervisor@ramcar.com'));

    expect(ApProcessor\WorkQueue::canView())->toBeFalse()
        ->and((new Dashboard)->getWidgets())->not->toContain(ApProcessor\WorkQueue::class);

    Livewire::test(Dashboard::class)->assertActionHidden('openRfpQueue');
});
