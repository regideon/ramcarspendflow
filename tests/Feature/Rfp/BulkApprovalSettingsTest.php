<?php

use App\Enums\RoleName;
use App\Filament\Clusters\RfpSettings\Pages\BulkApproval;
use App\Models\Rfp\Setting;
use App\Models\User;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndUserSeeder::class);
});

it('starts at 200K per RFP and 500K per batch', function () {
    expect(Setting::current())
        ->bulk_approval_item_limit->toBe(200000.0)
        ->bulk_approval_total_limit->toBe(500000.0);
});

it('lets Admins change the mass approval limits', function () {
    $this->actingAs(User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::Admin)));

    Livewire::test(BulkApproval::class)
        ->assertSchemaStateSet(['bulk_approval_item_limit' => 200000.0, 'bulk_approval_total_limit' => 500000.0])
        ->fillForm(['bulk_approval_item_limit' => 150000, 'bulk_approval_total_limit' => 750000])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Bulk approval limits saved');

    expect(Setting::current())
        ->bulk_approval_item_limit->toBe(150000.0)
        ->bulk_approval_total_limit->toBe(750000.0);
});

it('needs the batch total to be at least the per-RFP limit', function () {
    $this->actingAs(User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::Admin)));

    Livewire::test(BulkApproval::class)
        ->fillForm(['bulk_approval_item_limit' => 300000, 'bulk_approval_total_limit' => 100000])
        ->call('save')
        ->assertHasFormErrors(['bulk_approval_total_limit' => 'min']);

    expect(Setting::current()->bulk_approval_total_limit)->toBe(500000.0);
});

it('is for Admins only', function () {
    $this->actingAs(User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::ApManager)))
        ->get(BulkApproval::getUrl())
        ->assertForbidden();
});
