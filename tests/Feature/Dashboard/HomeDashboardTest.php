<?php

use App\Enums\RoleName;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Home;
use App\Filament\Resources\P2p\Approvals\ApprovalResource;
use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Models\User;
use Database\Seeders\CompanySeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([CompanySeeder::class, RoleAndUserSeeder::class]);

    $this->processor = User::firstWhere('email', 'ap.processor@ramcar.com');
});

it('shows the AP Processor the redesigned dashboard with every guide stop on the page', function () {
    $response = $this->actingAs($this->processor)
        ->get(Home::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['Good', 'AP Processor', '18 items'])
        ->assertSee('4 overdue')
        ->assertSeeInOrder(['Back on your desk', 'RFP first review', 'Work queue', 'Queue aging', 'Keeping up', 'Escalations raised', 'Tax summary', 'Insights'])
        ->assertSee('Guide');

    foreach ((new Home)->guideSteps() as $step) {
        $response->assertSee('data-tour="'.$step['target'].'"', escape: false);
    }

    Livewire::test(Home::class)
        ->assertActionHasUrl('openRfpQueue', RfpApprovalResource::getUrl('index'))
        ->assertActionHasUrl('processP2pInvoices', ApprovalResource::getUrl('index'));
});

it('labels the old dashboard as OLD for the AP Processor only', function () {
    $this->actingAs($this->processor);

    expect(Dashboard::getNavigationLabel())->toBe('Dashboard (OLD)');

    $this->actingAs(User::firstWhere('email', 'ap.supervisor@ramcar.com'));

    expect(Dashboard::getNavigationLabel())->toBe('Dashboard');
});

it('keeps the redesigned dashboard away from roles without one yet', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)->get(Home::getUrl())->assertForbidden();
})->with([RoleName::ApSupervisor->value, RoleName::Treasury->value, RoleName::Requestor->value]);
