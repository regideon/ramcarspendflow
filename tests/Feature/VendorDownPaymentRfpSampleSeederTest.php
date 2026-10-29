<?php

use App\Enums\RfpStatus;
use App\Models\P2p\Invoice;
use App\Models\Rfp\Item;
use App\Models\Rfp\Rfp;
use Database\Seeders\AccountGroupSeeder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\DemoMasterDataSeeder;
use Database\Seeders\P2p\P2pApprovalSampleSeeder;
use Database\Seeders\P2p\VendorDownPaymentRfpSampleSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\Rfp\ApprovalMatrixSeeder;
use Database\Seeders\Rfp\DueTypeSeeder;
use Database\Seeders\Rfp\WorkflowSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\TaxCodeSeeder;
use Database\Seeders\WithholdingTaxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->seed([
        CompanySeeder::class, CurrencySeeder::class, AccountGroupSeeder::class, PaymentMethodSeeder::class,
        TaxCodeSeeder::class, WithholdingTaxSeeder::class, DemoMasterDataSeeder::class, RoleAndUserSeeder::class,
        P2pApprovalSampleSeeder::class, ApprovalMatrixSeeder::class, WorkflowSeeder::class, DueTypeSeeder::class,
    ]);
});

it('seeds completed down payment RFPs for the vendor and PO of the P2P sample invoice', function () {
    $this->seed(VendorDownPaymentRfpSampleSeeder::class);

    $invoice = Invoice::query()->whereJsonContains('po_numbers', P2pApprovalSampleSeeder::PO_NUMBER)->sole();
    $rfps = Rfp::query()->with(['items', 'histories'])->whereHas('items', fn ($query) => $query->where('invoice_number', 'like', 'DP-SAMPLE-%'))->get();

    expect($rfps)->toHaveCount(count(VendorDownPaymentRfpSampleSeeder::DOWN_PAYMENTS))
        ->and($rfps->every(fn (Rfp $rfp): bool => $rfp->status === RfpStatus::Completed
            && $rfp->vendor_id === $invoice->vendor_id
            && $rfp->company_id === $invoice->company_id
            && $rfp->items->every(fn (Item $item): bool => $item->po_number === P2pApprovalSampleSeeder::PO_NUMBER && $item->subcategory->requires_po_number)))->toBeTrue()
        ->and($rfps->first()->histories->pluck('action')->filter()->values()->all())->toContain('Submitted', 'Payment released (Treasury)');

    Storage::disk('local')->assertExists($rfps->first()->items->first()->attachments[0]);
});

it('can be run again without duplicating its RFPs', function () {
    $this->seed(VendorDownPaymentRfpSampleSeeder::class);
    $this->seed(VendorDownPaymentRfpSampleSeeder::class);

    expect(Item::query()->where('invoice_number', 'like', 'DP-SAMPLE-%')->count())->toBe(count(VendorDownPaymentRfpSampleSeeder::DOWN_PAYMENTS));
});
