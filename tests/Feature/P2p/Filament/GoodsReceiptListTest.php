<?php

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Resources\P2p\GoodsReceipts\Pages\ListGoodsReceipts;
use App\Models\Company;
use App\Models\P2p\GoodReceiptState;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndUserSeeder::class);
});

/**
 * @return array{0: User, 1: Vendor, 2: Company}
 */
function grListContext(): array
{
    $vendor = Vendor::factory()->create();
    $company = Company::factory()->create();
    $user = User::factory()->create(['vendor_id' => $vendor->id]);
    $user->assignRole(RoleName::Vendor);

    return [$user, $vendor, $company];
}

function grListState(Vendor $vendor, Company $company, string $purchaseOrder, string $grNumber, P2pStatus $status = P2pStatus::Pending): GoodReceiptState
{
    return GoodReceiptState::create([
        'purchase_order' => $purchaseOrder,
        'good_receipt_number' => $grNumber,
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'status' => $status,
    ]);
}

it('deep-links the list to a purchase order', function () {
    [$user, $vendor, $company] = grListContext();
    $this->actingAs($user);

    $mine = grListState($vendor, $company, '4500000100', '5000000100');
    $other = grListState($vendor, $company, '4500000101', '5000000101');

    Livewire::withQueryParams([
        'activeTab' => 'all',
        'purchase_orders' => ['4500000100'],
    ])
        ->test(ListGoodsReceipts::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$other]);
});

it('matches a GR number against its partially-billed suffix', function () {
    [$user, $vendor, $company] = grListContext();
    $this->actingAs($user);

    $partial = grListState($vendor, $company, '4500000200', '5000000200-P1');
    $other = grListState($vendor, $company, '4500000201', '5000000201');

    Livewire::withQueryParams([
        'activeTab' => 'all',
        'gr_numbers' => ['5000000200'],
    ])
        ->test(ListGoodsReceipts::class)
        ->assertCanSeeTableRecords([$partial])
        ->assertCanNotSeeTableRecords([$other]);
});

it('scopes the tab badges to the deep-link', function () {
    [$user, $vendor, $company] = grListContext();
    $this->actingAs($user);

    grListState($vendor, $company, '4500000300', '5000000300');
    grListState($vendor, $company, '4500000301', '5000000301', P2pStatus::Rejected);

    $tabs = Livewire::withQueryParams([
        'activeTab' => 'all',
        'purchase_orders' => ['4500000300'],
    ])
        ->test(ListGoodsReceipts::class)
        ->instance()
        ->getCachedTabs();

    expect($tabs['pending']->getBadge())->toBe('1')
        ->and($tabs['rejected']->getBadge())->toBe('0')
        ->and($tabs['all']->getBadge())->toBe('1');
});

it('filters rows by the active status tab', function () {
    [$user, $vendor, $company] = grListContext();
    $this->actingAs($user);

    $pending = grListState($vendor, $company, '4500000400', '5000000400');
    $reversed = grListState($vendor, $company, '4500000401', '5000000401', P2pStatus::Reversed);

    Livewire::withQueryParams(['activeTab' => 'rejected'])
        ->test(ListGoodsReceipts::class)
        ->assertCanSeeTableRecords([$reversed])
        ->assertCanNotSeeTableRecords([$pending]);
});

it('lands on the pending tab by default', function () {
    [$user] = grListContext();
    $this->actingAs($user);

    Livewire::test(ListGoodsReceipts::class)
        ->assertRedirectContains('activeTab=pending');
});

it('only lets pending goods receipts be selected for processing', function () {
    [$user, $vendor, $company] = grListContext();
    $this->actingAs($user);

    $pending = grListState($vendor, $company, '4500000500', '5000000500');
    $submitted = grListState($vendor, $company, '4500000501', '5000000501', P2pStatus::SubmittedToAp);

    $table = Livewire::withQueryParams(['activeTab' => 'all'])
        ->test(ListGoodsReceipts::class)
        ->instance()
        ->getTable();

    expect($table->isRecordSelectable($pending))->toBeTrue()
        ->and($table->isRecordSelectable($submitted))->toBeFalse();
});

it('links back to purchase orders', function () {
    [$user] = grListContext();
    $this->actingAs($user);

    Livewire::withQueryParams(['activeTab' => 'all'])
        ->test(ListGoodsReceipts::class)
        ->assertActionExists('back');
});

it('hides the amount column from vendors', function () {
    [$user] = grListContext();
    $this->actingAs($user);

    Livewire::withQueryParams(['activeTab' => 'all'])
        ->test(ListGoodsReceipts::class)
        ->assertTableColumnHidden('amount_total');
});

it('shows the amount column to staff', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::Admin));

    Livewire::withQueryParams(['activeTab' => 'all'])
        ->test(ListGoodsReceipts::class)
        ->assertTableColumnVisible('amount_total');
});
