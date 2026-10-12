<?php

use App\Models\P2p\MaterialClassification;
use App\Models\P2p\SlocClassification;
use App\Models\Vendor;
use Database\Seeders\CompanySeeder;
use Database\Seeders\P2p\P2pDemoMasterDataSeeder;
use Database\Seeders\P2p\P2pSlocMasterSeeder;
use Database\Seeders\P2p\P2pVendorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CompanySeeder::class);
});

it('imports the SLOC master from the T001L extract', function () {
    $this->seed(P2pDemoMasterDataSeeder::class);
    $this->seed(P2pSlocMasterSeeder::class);

    $unclassified = MaterialClassification::query()->where('code', 'unclassified')->value('id');

    expect(SlocClassification::count())->toBeGreaterThan(100)
        ->and(SlocClassification::query()->where('p2p_material_classification_id', $unclassified)->exists())->toBeTrue();
});

it('re-runs the SLOC import without duplicating rows', function () {
    $this->seed(P2pDemoMasterDataSeeder::class);
    $this->seed(P2pSlocMasterSeeder::class);
    $count = SlocClassification::count();

    $this->seed(P2pSlocMasterSeeder::class);

    expect(SlocClassification::count())->toBe($count);
});

it('imports the vendor master and links vendors to their companies', function () {
    $this->seed(P2pVendorSeeder::class);

    $vendor = Vendor::query()->where('code', '200000')->first();

    expect(Vendor::count())->toBeGreaterThan(1000)
        ->and($vendor)->not->toBeNull()
        ->and(DB::table('company_vendor')->count())->toBeGreaterThan(0);
});

it('re-runs the vendor import without duplicating rows', function () {
    $this->seed(P2pVendorSeeder::class);
    $vendors = Vendor::count();
    $links = DB::table('company_vendor')->count();

    $this->seed(P2pVendorSeeder::class);

    expect(Vendor::count())->toBe($vendors)
        ->and(DB::table('company_vendor')->count())->toBe($links);
});
