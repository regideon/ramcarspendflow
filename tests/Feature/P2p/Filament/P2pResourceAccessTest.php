<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndUserSeeder::class);
});

dataset('p2p settings pages', [
    '/hub/p2p-settings/material-classifications',
    '/hub/p2p-settings/sloc-classifications',
    '/hub/p2p-settings/items',
    '/hub/p2p-settings/delivery-types',
    '/hub/p2p-settings/escalation-reasons',
    '/hub/p2p-settings/email-notification-receivers',
]);

it('lets admins open a P2P settings page', function (string $url) {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::Admin))
        ->get($url)
        ->assertOk();
})->with('p2p settings pages');

it('forbids non-admins from P2P settings', function (string $url) {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor))
        ->get($url)
        ->assertForbidden();
})->with('p2p settings pages');

it('lets a vendor open purchase orders', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::Vendor))
        ->get('/hub/p2p/purchase-orders')
        ->assertOk();
});

it('forbids a non-vendor from purchase orders', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor))
        ->get('/hub/p2p/purchase-orders')
        ->assertForbidden();
});
