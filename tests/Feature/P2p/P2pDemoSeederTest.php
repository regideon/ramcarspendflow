<?php

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Resources\P2p\Escalations\Pages\ListEscalations;
use App\Models\Company;
use App\Models\P2p\DeliveryType;
use App\Models\P2p\Escalation;
use App\Models\P2p\EscalationReason;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\Item;
use App\Models\P2p\MaterialClassification;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\User;
use Database\Seeders\CompanySeeder;
use Database\Seeders\P2p\P2pCompanySeeder;
use Database\Seeders\P2p\P2pDeliveryTypeSeeder;
use Database\Seeders\P2p\P2pDemoMasterDataSeeder;
use Database\Seeders\P2p\P2pDemoTransactionSeeder;
use Database\Seeders\P2p\P2pItemSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

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

    expect(SapPurchaseOrder::count())->toBe(11)
        ->and(Invoice::count())->toBe(6)
        ->and(GoodReceiptState::query()->where('status', P2pStatus::Reversed->value)->exists())->toBeTrue()
        ->and(Invoice::query()->where('status', P2pStatus::SubmittedToAp->value)->exists())->toBeTrue();
});

it('seeds open escalations for the escalated transactions', function () {
    $this->seed(P2pDemoMasterDataSeeder::class);
    $this->seed(P2pDemoTransactionSeeder::class);

    // Warehouse hop (Inv 4), procurement hop (Inv 5), and the partial header (Inv 6)
    // both hops: the Escalations queue and the stepper need real records.
    expect(Escalation::query()->where('status', Escalation::STATUS_OPEN)->count())->toBe(4)
        ->and(Escalation::query()->where('current_role', 'warehouse')->exists())->toBeTrue()
        ->and(Escalation::query()->where('current_role', 'procurement')->exists())->toBeTrue()
        ->and(
            Invoice::query()->where('status', P2pStatus::PartiallyEscalated->value)->firstOrFail()->gr_numbers,
        )->toHaveCount(2);
});

it('shows the seeded escalations to the hop teams on the escalations list', function () {
    $this->seed(P2pDemoMasterDataSeeder::class);
    $this->seed(P2pDemoTransactionSeeder::class);

    // Inv 5 (procurement hop) and Inv 6 (partial: procurement + warehouse hops).
    $this->actingAs(User::factory()->create()->assignRole(RoleName::Procurement));

    Livewire::test(ListEscalations::class)
        ->assertOk()
        ->assertSee('5000000006')
        ->assertSee('5000000007');

    // Inv 4 (warehouse hop) and Inv 6's second GR.
    $this->actingAs(User::factory()->create()->assignRole(RoleName::Warehouse));

    Livewire::test(ListEscalations::class)
        ->assertOk()
        ->assertSee('5000000005')
        ->assertSee('5000000007-P1');
});

it('re-runs P2P transaction seeding without duplicating rows', function () {
    $this->seed(P2pDemoMasterDataSeeder::class);
    $this->seed(P2pDemoTransactionSeeder::class);
    $this->seed(P2pDemoTransactionSeeder::class);

    expect(SapPurchaseOrder::count())->toBe(11)
        ->and(Invoice::count())->toBe(6);
});
