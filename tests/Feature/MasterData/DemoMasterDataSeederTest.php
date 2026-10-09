<?php

use App\Models\Company;
use App\Models\CostCenter;
use App\Models\ExchangeRate;
use App\Models\GlAccount;
use App\Models\Rfp\Category;
use App\Models\Rfp\OneTimeVendor;
use App\Models\Rfp\Subcategory;
use App\Models\Vendor;
use Database\Seeders\AccountGroupSeeder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\DemoMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([CompanySeeder::class, CurrencySeeder::class, AccountGroupSeeder::class, DemoMasterDataSeeder::class]);
});

it('seeds generic master data for every company', function () {
    expect(GlAccount::count())->toBe(20)
        ->and(Category::count())->toBe(11)
        ->and(Subcategory::count())->toBe(20)
        ->and(Vendor::count())->toBe(12) // 10 suppliers + 2 employee accounts
        ->and(OneTimeVendor::count())->toBe(2)
        ->and(ExchangeRate::count())->toBe(4);

    Company::all()->each(fn (Company $company) => expect($company->costCenters()->count())->toBe(4));
});

it('extends every vendor to every company', function () {
    Vendor::with('companies')->get()
        ->each(fn (Vendor $vendor) => expect($vendor->companies)->toHaveCount(Company::count()));
});

it('gives every cost center allowed G/L accounts of its expense type', function () {
    CostCenter::with('glAccounts')->get()->each(function (CostCenter $costCenter): void {
        expect($costCenter->glAccounts)->not->toBeEmpty()
            ->and($costCenter->glAccounts->every(fn ($glAccount): bool => $costCenter->allowsGlCode($glAccount->code)))->toBeTrue();
    });
});

it('can be re-run without duplicating data', function () {
    $counts = fn (): array => [GlAccount::count(), CostCenter::count(), Category::count(), Subcategory::count(), Vendor::count(), OneTimeVendor::count(), ExchangeRate::count()];
    $firstRun = $counts();

    $this->seed(DemoMasterDataSeeder::class);

    expect($counts())->toBe($firstRun);
});

it('refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    app(DemoMasterDataSeeder::class)->run();
})->throws(RuntimeException::class);
