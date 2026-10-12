<?php

use App\Enums\P2pStatus;
use App\Models\Company;
use App\Models\P2p\DeliveryType;
use App\Models\P2p\EscalationReason;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\Item;
use App\Models\P2p\MaterialClassification;
use App\Models\P2p\SapGoodReceiptItem;
use App\Models\P2p\SapPurchaseOrder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\P2p\P2pCompanySeeder;
use Database\Seeders\P2p\P2pDeliveryTypeSeeder;
use Database\Seeders\P2p\P2pDemoMasterDataSeeder;
use Database\Seeders\P2p\P2pDemoTransactionSeeder;
use Database\Seeders\P2p\P2pItemSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CompanySeeder::class);
    $this->seed(RoleAndUserSeeder::class);
});

it('seeds P2P master data', function () {
    $this->seed(P2pCompanySeeder::class);
    $this->seed(P2pDemoMasterDataSeeder::class);
    $this->seed(P2pItemSeeder::class);
    $this->seed(P2pDeliveryTypeSeeder::class);

    expect(MaterialClassification::count())->toBe(3)
        ->and(Item::count())->toBe(2)
        ->and(DeliveryType::count())->toBe(2)
        ->and(Company::count())->toBe(51)
        ->and(EscalationReason::count())->toBe(7);
});

it('seeds the data-driven escalation routes', function () {
    $this->seed(P2pDemoMasterDataSeeder::class);

    $priceDifference = EscalationReason::query()->where('code', 'price_difference')->firstOrFail();

    expect($priceDifference->steps)->toHaveCount(4)
        ->and($priceDifference->steps->last()->role)->toBe('vendor')
        ->and($priceDifference->steps->last()->override_header_status)->toBeTrue();

    expect(EscalationReason::query()->where('code', 'document_concern')->firstOrFail()->is_active)->toBeFalse();
});

it('re-runs P2P master data seeding without duplicating rows', function () {
    $this->seed(P2pDemoMasterDataSeeder::class);
    $this->seed(P2pDemoMasterDataSeeder::class);

    expect(MaterialClassification::count())->toBe(3)
        ->and(EscalationReason::count())->toBe(7)
        ->and(EscalationReason::query()->where('code', 'price_difference')->firstOrFail()->steps)->toHaveCount(4);
});

it('seeds demo transactions across the P2P statuses', function () {
    $this->seed(P2pDemoMasterDataSeeder::class);
    $this->seed(P2pDemoTransactionSeeder::class);

    $impeller = SapGoodReceiptItem::query()->with('item')->where('purchase_order', '4100382699')->firstOrFail();

    expect(SapPurchaseOrder::count())->toBe(16) // 11 status scenarios + 5 shaped like sample vendor invoices
        ->and(Invoice::count())->toBe(6)
        ->and(GoodReceiptState::query()->where('status', P2pStatus::Reversed->value)->exists())->toBeTrue()
        ->and(Invoice::query()->where('status', P2pStatus::SubmittedToAp->value)->exists())->toBeTrue()
        ->and($impeller->item->code)->toBe('E7877051')
        ->and($impeller->currency)->toBe('EUR')
        ->and($impeller->poUnitPrice())->toBe('580.2800');
});

it('re-runs P2P transaction seeding without duplicating rows', function () {
    $this->seed(P2pDemoMasterDataSeeder::class);
    $this->seed(P2pDemoTransactionSeeder::class);
    $this->seed(P2pDemoTransactionSeeder::class);

    expect(SapPurchaseOrder::count())->toBe(16)
        ->and(Invoice::count())->toBe(6);
});
