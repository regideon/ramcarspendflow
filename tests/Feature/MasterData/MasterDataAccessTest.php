<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndUserSeeder::class);
});

dataset('admin pages', [
    '/hub/master-data/companies',
    '/hub/master-data/currencies',
    '/hub/master-data/exchange-rates',
    '/hub/master-data/account-groups',
    '/hub/master-data/payment-methods',
    '/hub/master-data/gl-accounts',
    '/hub/master-data/cost-centers',
    '/hub/master-data/tax-codes',
    '/hub/master-data/withholding-tax-types',
    '/hub/master-data/withholding-tax-codes',
    '/hub/master-data/vendors',
    '/hub/rfp-settings/approval-rules',
    '/hub/rfp-settings/categories',
    '/hub/users',
    '/hub/roles',
]);

it('lets admins open the page', function (string $url) {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::Admin))
        ->get($url)
        ->assertOk();
})->with('admin pages');

it('forbids non-admins', function (string $url) {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor))
        ->get($url)
        ->assertForbidden();
})->with('admin pages');

it('blocks inactive users from the panel', function () {
    $this->actingAs(User::factory()->inactive()->create()->assignRole(RoleName::Admin))
        ->get('/hub')
        ->assertForbidden();
});
