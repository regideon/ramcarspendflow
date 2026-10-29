<?php

use App\Enums\RoleName;
use App\Filament\Pages\Auth\EditProfile;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\CompanySeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([CompanySeeder::class, RoleAndUserSeeder::class]);
});

it('shows the signed-in user their roles and the companies of each approval role', function () {
    $pbi = Company::firstWhere('code', '1000');
    $ommc = Company::firstWhere('code', '2000');

    $user = User::factory()->create();
    $user->assignRole(RoleName::Requestor->value);
    $user->syncCompanyRoles([
        ['role' => RoleName::LineManager, 'companies' => [$pbi->id, $ommc->id]],
        ['role' => RoleName::DepartmentHead, 'companies' => [$ommc->id]],
    ]);

    $this->actingAs($user->fresh())
        ->get('/hub/profile')
        ->assertOk()
        ->assertSeeInOrder(['Your roles', 'Roles', RoleName::Requestor->value, RoleName::DepartmentHead->value, $ommc->short_name, RoleName::LineManager->value, $ommc->short_name, $pbi->short_name]);
});

it('says when the user has no approval roles, and still saves the profile', function () {
    $user = User::factory()->create(['name' => 'Old Name']);
    $user->assignRole(RoleName::Requestor->value);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->assertSee('Approval roles, per company')
        ->assertSee('None')
        ->fillForm(['name' => 'New Name'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->name)->toBe('New Name');
});
