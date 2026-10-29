<?php

use App\Enums\RoleName;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Filament\Resources\Rfps\RfpResource;
use App\Models\User;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndUserSeeder::class);
});

// docs/13-hidden-pages.md
it('hides the old RFP lists from the menu for everyone', function () {
    expect(RfpResource::shouldRegisterNavigation())->toBeFalse()
        ->and(RfpApprovalResource::shouldRegisterNavigation())->toBeFalse();

    $this->actingAs(User::factory()->create()->assignRole(RoleName::Admin))
        ->get('/hub/rfp/my-requests')
        ->assertOk()
        ->assertDontSee('RFP (OLD)')
        ->assertDontSee('href="http://localhost/hub/rfps"', escape: false)
        ->assertDontSee('href="http://localhost/hub/rfp-approvals"', escape: false);
});

it('still opens the hidden pages from a link', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::Admin));

    $this->get('/hub/rfps')->assertOk();
    $this->get('/hub/rfp-approvals')->assertOk();
});

it('hides the old dashboard from the menu for everyone, but /hub still opens', function (RoleName $role) {
    expect(Dashboard::shouldRegisterNavigation())->toBeFalse();

    $this->actingAs(User::factory()->create()->assignRole($role))
        ->get('/hub')
        ->assertOk()
        ->assertDontSee('Dashboard (OLD)');
})->with([RoleName::ApProcessor, RoleName::Requestor, RoleName::Admin]);
