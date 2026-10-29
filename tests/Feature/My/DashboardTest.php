<?php

use App\Enums\RoleName;
use App\Models\CompanyRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

it('shows matrix approvers their approvals above their requests', function () {
    $approver = User::factory()->create();
    CompanyRole::factory()->for($approver)->create(['role' => RoleName::DepartmentHead]);

    $this->actingAs($approver)
        ->get(route('filament.my.pages.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Awaiting your approval', 'Needs your action', 'My requests']);
});

it('shows requestors only their requests', function () {
    $requestor = User::factory()->create();
    CompanyRole::factory()->for($requestor)->create(['role' => RoleName::ApProcessor]);

    $this->actingAs($requestor)
        ->get(route('filament.my.pages.dashboard'))
        ->assertOk()
        ->assertSee('My requests')
        ->assertDontSee('Awaiting your approval')
        ->assertDontSee('Approvals');
});

it('sends guests to the panel login', function () {
    $this->get(route('filament.my.pages.dashboard'))
        ->assertRedirect(route('filament.my.auth.login'));
});

it('has a notifications panel opened from the bell', function () {
    $approver = User::factory()->create();
    CompanyRole::factory()->for($approver)->create(['role' => RoleName::DepartmentHead]);

    $this->actingAs($approver)
        ->get(route('filament.my.pages.dashboard'))
        ->assertOk()
        ->assertSee("\$dispatch('open-notifications')", escape: false)
        ->assertSeeInOrder(['Notifications', 'Today', 'Overdue: waiting for your approval', 'Your RFP was returned', 'Yesterday', 'Earlier', 'Your RFP was rejected'])
        ->assertSee('Notification settings');
});

it('shows requestors no approval notifications', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('filament.my.pages.dashboard'))
        ->assertOk()
        ->assertSee('Your RFP was returned')
        ->assertDontSee('waiting for your approval');
});

it('sends Review now to the approvals list', function () {
    $approver = User::factory()->create();
    CompanyRole::factory()->for($approver)->create(['role' => RoleName::DepartmentHead]);

    $this->actingAs($approver)
        ->get(route('filament.my.pages.dashboard'))
        ->assertOk()
        ->assertSeeInOrder([route('filament.my.pages.approvals'), 'Review now'], escape: false);
});
