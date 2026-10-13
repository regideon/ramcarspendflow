<?php

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Resources\P2p\Approvals\ApprovalResource;
use App\Models\P2p\GoodReceiptState;
use App\Models\P2p\Invoice;
use App\Models\P2p\SapPurchaseOrder;
use App\Models\User;
use Database\Seeders\CompanySeeder;
use Database\Seeders\P2p\P2pApprovalSampleSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\TaxCodeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $this->seed(CompanySeeder::class);
    $this->seed(TaxCodeSeeder::class);
    $this->seed(RoleAndUserSeeder::class);
});

function sampleApproval(): Invoice
{
    return Invoice::query()
        ->where('invoice_number', P2pApprovalSampleSeeder::INVOICE_NUMBER)
        ->firstOrFail();
}

it('seeds an AP approval whose PO, GR and invoice lines agree', function () {
    $this->seed(P2pApprovalSampleSeeder::class);

    $invoice = sampleApproval();
    $po = SapPurchaseOrder::query()->where('purchase_order', P2pApprovalSampleSeeder::PO_NUMBER)->firstOrFail();
    $state = GoodReceiptState::query()->where('good_receipt_number', P2pApprovalSampleSeeder::GR_NUMBER)->firstOrFail();

    expect($invoice->status)->toBe(P2pStatus::SubmittedToAp)
        ->and($po->gr_numbers)->toBe([P2pApprovalSampleSeeder::GR_NUMBER])
        ->and($state->purchase_order)->toBe(P2pApprovalSampleSeeder::PO_NUMBER)
        ->and($invoice->lines)->toHaveCount(3)
        // The PO/GR totals and the summed lines are the same money the PDF shows.
        ->and((float) $po->total_amount)->toBe((float) $invoice->total)
        ->and((float) $invoice->vatable_sales)->toBe((float) $invoice->lines->sum(fn ($line): float => (float) $line->amount));
});

it('attaches a sample PDF that repeats the invoice information', function () {
    $this->seed(P2pApprovalSampleSeeder::class);

    $path = sampleApproval()->attachments[0] ?? null;

    expect($path)->toBe('attachments-pdf/'.P2pApprovalSampleSeeder::INVOICE_NUMBER.'.pdf')
        ->and(Storage::disk('public')->exists($path))->toBeTrue();

    $pdf = Storage::disk('public')->get($path);

    expect($pdf)->toStartWith('%PDF')
        ->toContain(P2pApprovalSampleSeeder::PO_NUMBER)
        ->toContain(P2pApprovalSampleSeeder::GR_NUMBER)
        ->toContain(P2pApprovalSampleSeeder::INVOICE_NUMBER)
        ->toContain('Lead Ingot');
});

it('queues the seeded approval for the AP processor', function () {
    $this->seed(P2pApprovalSampleSeeder::class);

    $invoice = sampleApproval();

    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor));

    expect(ApprovalResource::getEloquentQuery()->pluck('id')->all())->toContain($invoice->id);
});

it('re-runs without duplicating the sample approval', function () {
    $this->seed(P2pApprovalSampleSeeder::class);
    $this->seed(P2pApprovalSampleSeeder::class);

    expect(Invoice::query()->where('invoice_number', P2pApprovalSampleSeeder::INVOICE_NUMBER)->count())->toBe(1)
        ->and(SapPurchaseOrder::query()->where('purchase_order', P2pApprovalSampleSeeder::PO_NUMBER)->count())->toBe(1);
});
