<?php

use App\Enums\RoleName;
use App\Filament\My\Pages\Profile;
use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->home = Company::factory()->create(['name' => 'Ramcar Inc.']);
    $this->user = User::factory()->create(['name' => 'Reg Ebalobor', 'company_id' => $this->home->id, 'password' => 'old-password']);

    $this->actingAs($this->user);
    Filament::setCurrentPanel('my');
});

it('shows the user and their roles per company, home company first', function () {
    $other = Company::factory()->create(['name' => 'Aardvark Holdings']);
    CompanyRole::factory()->for($this->user)->for($other)->create(['role' => RoleName::Requestor]);
    CompanyRole::factory()->for($this->user)->for($this->home)->create(['role' => RoleName::DepartmentHead]);

    $this->get(route('filament.my.pages.profile'))
        ->assertOk()
        ->assertSee('Reg Ebalobor')
        ->assertSee('RE')
        ->assertSeeInOrder(['My roles', 'Ramcar Inc.', 'Home company', 'Department Head', 'Aardvark Holdings', 'Requestor'])
        ->assertSee('Change password')
        ->assertSee('Sign out');
});

it('says when the user has no roles', function () {
    $this->get(route('filament.my.pages.profile'))
        ->assertOk()
        ->assertSee('No roles yet');
});

it('changes the password and keeps the user signed in', function () {
    Livewire::test(Profile::class)
        ->set('passwordForm.current', 'old-password')
        ->set('passwordForm.password', 'New-password-2026')
        ->set('passwordForm.password_confirmation', 'New-password-2026')
        ->call('updatePassword')
        ->assertReturned(true)
        ->assertHasNoErrors()
        ->assertSet('passwordForm.password', '')
        ->assertNotified('Password updated');

    expect(Hash::check('New-password-2026', $this->user->fresh()->password))->toBeTrue()
        ->and(session('password_hash_web'))->toBe($this->user->fresh()->password);
});

it('needs the current password and a confirmed new one', function () {
    Livewire::test(Profile::class)
        ->set('passwordForm.current', 'wrong')
        ->set('passwordForm.password', 'short')
        ->set('passwordForm.password_confirmation', 'other')
        ->call('updatePassword')
        ->assertReturned(false)
        ->assertHasErrors(['passwordForm.current', 'passwordForm.password']);

    expect(Hash::check('old-password', $this->user->fresh()->password))->toBeTrue();
});
