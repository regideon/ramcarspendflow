<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\Rfp\Requestor;
use App\Models\User;
use Database\Seeders\CompanySeeder;
use Database\Seeders\RoleAndUserSeeder;
use Filament\Widgets\AccountWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([CompanySeeder::class, RoleAndUserSeeder::class]);

    $this->requestor = User::firstWhere('email', 'requestor@ramcar.com');
});

it('shows the requestor their own dashboard', function () {
    $this->actingAs($this->requestor)
        ->get(Dashboard::getUrl())
        ->assertOk()
        ->assertSee("Here's where your payment requests stand.");

    Livewire::test(Dashboard::class)
        ->assertActionVisible('newStandard')
        ->assertActionVisible('newReimbursement');

    expect((new Dashboard)->getWidgets())
        ->toContain(Requestor\RequestsInFlight::class)
        ->not->toContain(AccountWidget::class);
});

it('renders every requestor widget', function (string $widget) {
    $this->actingAs($this->requestor);

    Livewire::test($widget)->assertOk();
})->with([
    Requestor\ActionNeeded::class,
    Requestor\RequestorStats::class,
    Requestor\RequestsInFlight::class,
    Requestor\Pipeline::class,
    Requestor\StageWait::class,
    Requestor\SpendOverTime::class,
    Requestor\UpcomingDue::class,
    Requestor\Insights::class,
]);

it('keeps the requestor widgets away from other roles', function () {
    $this->actingAs(User::firstWhere('email', 'ap.processor@ramcar.com'));

    expect((new Dashboard)->getWidgets())->not->toContain(Requestor\RequestsInFlight::class)
        ->and(Requestor\RequestsInFlight::canView())->toBeFalse();

    Livewire::test(Dashboard::class)->assertActionHidden('newStandard');
});
