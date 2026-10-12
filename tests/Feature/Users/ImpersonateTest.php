<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Database\Seeders\CompanySeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

it('opens the hub after impersonating a user', function () {
    $this->seed([CompanySeeder::class, RoleAndUserSeeder::class]);
    $this->actingAs(User::firstWhere('email', RoleAndUserSeeder::OWNER_EMAIL));

    Livewire::test(ListUsers::class)
        ->callTableAction('impersonate', User::firstWhere('email', 'requestor@ramcar.com'))
        ->assertRedirect(url('/hub'));
});
