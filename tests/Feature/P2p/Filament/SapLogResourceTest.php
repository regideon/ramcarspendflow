<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndUserSeeder::class);
});

it('lets an admin open the SAP log viewer', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::Admin))
        ->get('/hub/p2p/sap-logs')
        ->assertOk();
});

it('forbids non-admins from the SAP log viewer', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApSupervisor))
        ->get('/hub/p2p/sap-logs')
        ->assertForbidden();
});
