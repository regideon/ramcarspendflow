<?php

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Resources\P2p\Approvals\ApprovalResource;
use App\Models\Company;
use App\Models\P2p\Invoice;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndUserSeeder::class);
});

function apInvoice(P2pStatus $status, ?Vendor $vendor = null): Invoice
{
    return Invoice::factory()->create([
        'status' => $status,
        'vendor_id' => $vendor?->id ?? Vendor::factory(),
        'company_id' => Company::factory(),
        'submitted_at' => now()->subDay(),
    ]);
}

it('lets an AP processor and an admin open the approvals queue', function (RoleName $role) {
    $this->actingAs(User::factory()->create()->assignRole($role))
        ->get('/hub/p2p/approvals')
        ->assertOk();
})->with([
    'ap processor' => RoleName::ApProcessor,
    'admin' => RoleName::Admin,
]);

it('forbids a vendor and a requestor from approvals', function (RoleName $role) {
    $this->actingAs(User::factory()->create()->assignRole($role))
        ->get('/hub/p2p/approvals')
        ->assertForbidden();
})->with([
    'vendor' => RoleName::Vendor,
    'requestor' => RoleName::Requestor,
]);

it('lets an AP supervisor open approvals and the processing screen', function () {
    $supervisor = User::factory()->create()->assignRole(RoleName::ApSupervisor);
    $this->actingAs($supervisor);

    $this->get('/hub/p2p/approvals')->assertOk();

    $invoice = apInvoice(P2pStatus::SubmittedToChecker);
    $this->get('/hub/p2p/bulk-accounts-payable?invoice='.$invoice->id)->assertOk();
});

it('queues the checker queue for a supervisor and the processor queue for a processor', function () {
    $withProcessor = apInvoice(P2pStatus::SubmittedToAp);
    $withSupervisor = apInvoice(P2pStatus::SubmittedToChecker);

    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor));
    $processorIds = ApprovalResource::getEloquentQuery()->pluck('id')->all();

    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApSupervisor));
    $supervisorIds = ApprovalResource::getEloquentQuery()->pluck('id')->all();

    expect($processorIds)->toContain($withProcessor->id)
        ->and($processorIds)->not->toContain($withSupervisor->id)
        ->and($supervisorIds)->toContain($withSupervisor->id)
        ->and($supervisorIds)->not->toContain($withProcessor->id);
});

it('badges a supervisor with their own queue size', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApSupervisor));

    apInvoice(P2pStatus::SubmittedToChecker);
    apInvoice(P2pStatus::SubmittedToChecker);
    apInvoice(P2pStatus::SubmittedToAp);

    expect(ApprovalResource::getNavigationBadge())->toBe('2');
});

it('queues only submissions awaiting the AP processor', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor));

    $submitted = apInvoice(P2pStatus::SubmittedToAp);
    $returned = apInvoice(P2pStatus::ReturnedToApProcessor);
    $review = apInvoice(P2pStatus::ApReview);

    // Already handled, or waiting on someone else.
    $withChecker = apInvoice(P2pStatus::SubmittedToChecker);
    $parked = apInvoice(P2pStatus::InvoiceParked);
    $vendorPending = apInvoice(P2pStatus::Pending);

    $ids = ApprovalResource::getEloquentQuery()->pluck('id')->all();

    expect($ids)->toContain($submitted->id, $returned->id, $review->id)
        ->and($ids)->not->toContain($withChecker->id, $parked->id, $vendorPending->id);
});

it('badges the navigation with the queue size', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor));

    apInvoice(P2pStatus::SubmittedToAp);
    apInvoice(P2pStatus::SubmittedToAp);
    apInvoice(P2pStatus::InvoiceParked);

    expect(ApprovalResource::getNavigationBadge())->toBe('2');
});

it('hides the navigation badge when the queue is empty', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor));

    apInvoice(P2pStatus::InvoiceParked);

    expect(ApprovalResource::getNavigationBadge())->toBeNull();
});

it('opens the AP processing screen for a queued submission', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor));

    $invoice = apInvoice(P2pStatus::SubmittedToAp);

    $this->get('/hub/p2p/bulk-accounts-payable?invoice='.$invoice->id)->assertOk();
});

it('forbids a vendor from the AP processing screen', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::Vendor));

    $invoice = apInvoice(P2pStatus::SubmittedToAp);

    $this->get('/hub/p2p/bulk-accounts-payable?invoice='.$invoice->id)->assertForbidden();
});

it('sends an unknown submission back to the queue', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor));

    $this->get('/hub/p2p/bulk-accounts-payable?invoice=999999')
        ->assertRedirect(ApprovalResource::getUrl('index'));
});
