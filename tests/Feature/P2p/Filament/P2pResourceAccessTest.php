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

it('lets a vendor open goods receipts and the bulk page', function () {
    $vendor = User::factory()->create()->assignRole(RoleName::Vendor);

    // A bare visit lands on the Pending tab (deep-link-friendly), so it redirects first.
    $this->actingAs($vendor)->get('/hub/p2p/goods-receipts')
        ->assertRedirectContains('activeTab=pending');

    $this->actingAs($vendor)->get('/hub/p2p/goods-receipts?activeTab=pending')->assertOk();
    $this->actingAs($vendor)->get('/hub/p2p/bulk-goods-receipt')->assertOk();
});

it('forbids a non-vendor from the goods receipt flow', function () {
    $processor = User::factory()->create()->assignRole(RoleName::ApProcessor);

    $this->actingAs($processor)->get('/hub/p2p/goods-receipts')->assertForbidden();
    $this->actingAs($processor)->get('/hub/p2p/bulk-goods-receipt')->assertForbidden();
});

it('lets vendors and AP processors open invoices', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::Vendor))
        ->get('/hub/p2p/invoices')->assertOk();

    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor))
        ->get('/hub/p2p/invoices')->assertOk();
});

it('forbids a requestor from invoices', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::Requestor))
        ->get('/hub/p2p/invoices')
        ->assertForbidden();
});

it('lets escalation handlers open escalations', function (RoleName $role) {
    $this->actingAs(User::factory()->create()->assignRole($role))
        ->get('/hub/p2p/escalations')
        ->assertOk();
})->with([
    'admin' => RoleName::Admin,
    'procurement' => RoleName::Procurement,
    'warehouse' => RoleName::Warehouse,
    'warehouse raw materials' => RoleName::WarehouseRawMaterials,
    'warehouse spare parts' => RoleName::WarehouseSpareParts,
]);

it('forbids a vendor from escalations', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::Vendor))
        ->get('/hub/p2p/escalations')
        ->assertForbidden();
});

it('removed the P2P detail screens in favour of the processing screens', function () {
    $admin = User::factory()->create()->assignRole(RoleName::Admin);

    // The three P2P resources expose no `view` page: rows link to the processing
    // screens (bulk goods receipt / GR list) instead of a record detail page.
    foreach (['/hub/p2p/invoices/1', '/hub/p2p/goods-receipts/1', '/hub/p2p/purchase-orders/1'] as $url) {
        $this->actingAs($admin)->get($url)->assertNotFound();
    }
});
