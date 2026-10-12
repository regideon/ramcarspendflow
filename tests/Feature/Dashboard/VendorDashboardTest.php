<?php

use App\Enums\P2pStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\P2p\Vendor;
use App\Filament\Widgets\Rfp\Requestor\RequestsInFlight;
use App\Jobs\P2p\SyncP2pPurchaseOrders;
use App\Models\User;
use App\Services\P2p\P2pVendorDashboard;
use Database\Seeders\CompanySeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([CompanySeeder::class, RoleAndUserSeeder::class]);

    $this->vendor = User::firstWhere('email', 'vendor@ramcar.com');
});

it('shows the vendor their own dashboard', function () {
    $this->actingAs($this->vendor)
        ->get(Dashboard::getUrl())
        ->assertOk()
        ->assertSee('Welcome back, '.$this->vendor->vendor->name);

    Livewire::test(Dashboard::class)
        ->assertActionVisible('billGoodsReceipts')
        ->assertActionVisible('syncFromSap')
        ->assertActionHidden('newStandard');

    expect((new Dashboard)->getWidgets())
        ->toContain(Vendor\MyInvoices::class)
        ->not->toContain(RequestsInFlight::class);
});

it('queues an SAP sync from the dashboard', function () {
    Queue::fake();
    $this->actingAs($this->vendor);

    Livewire::test(Dashboard::class)
        ->callAction('syncFromSap')
        ->assertNotified('Sync started');

    Queue::assertPushed(SyncP2pPurchaseOrders::class);
});

it('renders every vendor widget', function (string $widget) {
    $this->actingAs($this->vendor);

    Livewire::test($widget)->assertOk();
})->with([
    Vendor\ActionNeeded::class,
    Vendor\VendorStats::class,
    Vendor\ReadyToBill::class,
    Vendor\PaymentOutlook::class,
    Vendor\MyInvoices::class,
    Vendor\Escalations::class,
    Vendor\Turnaround::class,
    Vendor\Insights::class,
]);

it('never shows goods receipt pricing to the vendor', function () {
    $this->actingAs($this->vendor);

    foreach ((new P2pVendorDashboard($this->vendor))->readyToBill() as $receipt) {
        expect($receipt)->not->toHaveKeys(['amount', 'price', 'unit_price']);
    }

    Livewire::test(Vendor\ReadyToBill::class)->assertDontSee('₱');
});

it('maps every P2P status to a vendor stage and tab', function (P2pStatus $status) {
    $view = P2pVendorDashboard::vendorView($status);

    expect($view['stage'])->toBeBetween(1, 7)
        ->and(P2pVendorDashboard::GROUPS)->toHaveKey($view['group'])
        ->and($view['label'])->not->toContain('Checker');
})->with(P2pStatus::cases());

it('keeps the vendor widgets away from other roles', function () {
    $this->actingAs(User::firstWhere('email', 'requestor@ramcar.com'));

    expect(Vendor\MyInvoices::canView())->toBeFalse();

    Livewire::test(Dashboard::class)
        ->assertActionHidden('billGoodsReceipts')
        ->assertActionHidden('syncFromSap');
});
