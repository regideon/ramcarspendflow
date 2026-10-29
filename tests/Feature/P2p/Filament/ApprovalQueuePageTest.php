<?php

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Pages\P2p\ApprovalQueue;
use App\Filament\Resources\P2p\Approvals\ApprovalResource;
use App\Models\Company;
use App\Models\P2p\Invoice;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndUserSeeder::class);
});

function queueInvoice(P2pStatus $status, array $attributes = []): Invoice
{
    return Invoice::factory()->create([
        'status' => $status,
        'vendor_id' => Vendor::factory(),
        'company_id' => Company::factory(),
        'submitted_at' => now()->subDay(),
        ...$attributes,
    ])->refresh(); // grc_number is generated from the id
}

it('opens for AP roles and admins, not for requestors or vendors', function (RoleName $role, int $status) {
    $this->actingAs(User::factory()->create()->assignRole($role))
        ->get('/hub/p2p/approval-queue')
        ->assertStatus($status);
})->with([
    'ap processor' => [RoleName::ApProcessor, 200],
    'ap supervisor' => [RoleName::ApSupervisor, 200],
    'admin' => [RoleName::Admin, 200],
    'requestor' => [RoleName::Requestor, 403],
    'vendor' => [RoleName::Vendor, 403],
]);

it('shows the viewer\'s own queue on For my action, as the old list does', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor));

    $mine = queueInvoice(P2pStatus::SubmittedToAp);
    $checker = queueInvoice(P2pStatus::SubmittedToChecker);

    Livewire::test(ApprovalQueue::class)
        ->assertSee($mine->grc_number)
        ->assertDontSee($checker->grc_number)
        ->assertSet('tabCounts.mine', 1)
        ->call('setTab', 'ap_checker')
        ->assertSee($checker->grc_number)
        ->assertDontSee($mine->grc_number)
        ->call('setTab', 'all')
        ->assertSee($mine->grc_number)
        ->assertSee($checker->grc_number);
});

it('lists escalated and SAP-posted submissions on their own tabs', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor));

    $escalated = queueInvoice(P2pStatus::EscalateToProcurement);
    $parked = queueInvoice(P2pStatus::InvoiceParked);

    Livewire::test(ApprovalQueue::class, ['tab' => 'escalated'])
        ->assertSee($escalated->grc_number)
        ->assertDontSee($parked->grc_number)
        ->call('setTab', 'posted')
        ->assertSee($parked->grc_number)
        ->assertDontSee($escalated->grc_number);
});

it('searches, filters by status and by invoice review', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor));

    $acme = Vendor::factory()->create(['name' => 'Acme Industrial']);
    $acmeInvoice = queueInvoice(P2pStatus::SubmittedToAp, ['vendor_id' => $acme->id, 'match_flag_count' => 2]);
    $other = queueInvoice(P2pStatus::ReturnedToApProcessor);

    Livewire::test(ApprovalQueue::class)
        ->set('search', 'Acme')
        ->assertSee($acmeInvoice->grc_number)
        ->assertDontSee($other->grc_number)
        ->call('clearFilters')
        ->set('status', (string) P2pStatus::ReturnedToApProcessor->value)
        ->assertSee($other->grc_number)
        ->assertDontSee($acmeInvoice->grc_number)
        ->call('clearFilters')
        ->call('toggleNeedsReview')
        ->assertSee($acmeInvoice->grc_number)
        ->assertSee('2 to check')
        ->assertDontSee($other->grc_number);
});

it('works the queue oldest first and flags long waits', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor));

    $new = queueInvoice(P2pStatus::SubmittedToAp, ['submitted_at' => now()->subHour()]);
    $old = queueInvoice(P2pStatus::SubmittedToAp, ['submitted_at' => now()->subDays(8)]);

    Livewire::test(ApprovalQueue::class)
        ->assertSeeInOrder([$old->grc_number, $new->grc_number])
        ->assertSee('1 waiting more than 3 days')
        ->call('toggleOldestFirst')
        ->assertSeeInOrder([$new->grc_number, $old->grc_number]);
});

it('opens the redesigned review from Review', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor));

    $invoice = queueInvoice(P2pStatus::SubmittedToAp);

    Livewire::test(ApprovalQueue::class)
        ->assertSee('/hub/p2p/approval-queue/review?invoice='.$invoice->id, escape: false);
});

it('exports the current tab as CSV', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor));

    queueInvoice(P2pStatus::SubmittedToAp);

    Livewire::test(ApprovalQueue::class)
        ->call('export')
        ->assertFileDownloaded('p2p-approvals-mine.csv');
});

it('keeps the old list in the menu under the "-" group', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor));

    $this->get('/hub/p2p/approval-queue')
        ->assertOk()
        ->assertSee('/hub/p2p/approvals', escape: false);

    expect(ApprovalResource::getNavigationGroup())->toBe('-');
});
