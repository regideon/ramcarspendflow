<?php

use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Resources\RfpApprovals\Pages\ListRfpApprovals;
use App\Filament\Resources\Rfps\Pages\ListRfps;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Rfp\DueType;
use App\Models\Rfp\Item;
use App\Models\Rfp\Rfp;
use App\Models\User;
use Database\Seeders\AccountGroupSeeder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\DemoMasterDataSeeder;
use Database\Seeders\Rfp\DueTypeSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([CompanySeeder::class, CurrencySeeder::class, AccountGroupSeeder::class, DemoMasterDataSeeder::class, RoleAndUserSeeder::class, DueTypeSeeder::class]);

    $this->company = Company::firstWhere('code', '1000');
    $this->requestor = User::firstWhere('email', 'requestor@ramcar.com');
    $this->apProcessor = User::firstWhere('email', 'ap.processor@ramcar.com');
    $this->apSupervisor = User::firstWhere('email', 'ap.supervisor@ramcar.com');
    $this->apManager = User::firstWhere('email', 'ap.manager@ramcar.com');
});

/**
 * An RFP at `$status`, waiting for `$role`, due `$dueDate`, whose last status change was `$changedAt` (from `$from`).
 */
function queuedRfp(RfpStatus $status, ?RoleName $role, ?string $dueDate = null, array $attributes = [], ?RfpStatus $from = null, ?string $changedAt = null): Rfp
{
    $rfp = Rfp::factory()->for(test()->requestor, 'requestor')->for(test()->company)->create();
    $rfp->forceFill(['status' => $status, 'current_approver_role' => $role, 'due_date' => $dueDate ?? today()->addDays(5), ...$attributes])->saveQuietly();

    $history = $rfp->histories()->create(['from_status' => $from ?? RfpStatus::Draft, 'to_status' => $status]);
    $history->forceFill(['created_at' => $changedAt ?? now()])->saveQuietly();

    return $rfp;
}

function chipCounts($component): array
{
    return collect((fn () => $this->queueChipStates())->call($component->instance()))->mapWithKeys(fn (array $state) => [$state['chip']->key => $state['count']])->all();
}

it('shows the AP Processor what to work on first, and filters the table by chip', function () {
    $overdue = queuedRfp(RfpStatus::ForApReview, RoleName::ApProcessor, today()->subDays(2)->toDateString());
    $dueToday = queuedRfp(RfpStatus::ForApProcessing, RoleName::ApProcessor, today()->toDateString());
    $urgent = queuedRfp(RfpStatus::ForApReview, RoleName::ApProcessor, attributes: ['rfp_due_type_id' => DueType::firstWhere('code', 'urgent')->id]);
    $backOnDesk = queuedRfp(RfpStatus::ForApProcessing, RoleName::ApProcessor, from: RfpStatus::ForAccountingApproval);
    $notMine = queuedRfp(RfpStatus::ForApproval, RoleName::LineManager, today()->subDays(2)->toDateString());

    $this->actingAs($this->apProcessor);

    $page = Livewire::test(ListRfpApprovals::class);

    expect(chipCounts($page))->toBe(['overdue' => 1, 'due_today' => 1, 'urgent' => 1, 'back_on_desk' => 1]);

    $page->call('toggleQueueChip', 'overdue')
        ->assertSet('activeTab', 'mine')
        ->assertSet('focus', 'overdue')
        ->assertCanSeeTableRecords([$overdue])
        ->assertCanNotSeeTableRecords([$dueToday, $urgent, $backOnDesk, $notMine])
        ->call('toggleQueueChip', 'back_on_desk')
        ->assertCanSeeTableRecords([$backOnDesk])
        ->assertCanNotSeeTableRecords([$overdue])
        ->call('toggleQueueChip', 'back_on_desk')
        ->assertSet('focus', null)
        ->assertCanSeeTableRecords([$overdue, $dueToday, $urgent, $backOnDesk]);
});

it('clears the chip when another tab is picked', function () {
    queuedRfp(RfpStatus::ForApReview, RoleName::ApProcessor, today()->subDay()->toDateString());

    $this->actingAs($this->apProcessor);

    Livewire::test(ListRfpApprovals::class)
        ->call('toggleQueueChip', 'overdue')
        ->set('activeTab', 'all')
        ->assertSet('focus', null);
});

it('gives the AP Supervisor a waiting chip and the AP Manager a foreign currency chip', function () {
    $waiting = queuedRfp(RfpStatus::ForAccountingApproval, RoleName::ApSupervisor, changedAt: now()->subDays(4)->toDateTimeString());
    queuedRfp(RfpStatus::ForAccountingApproval, RoleName::ApSupervisor);

    $this->actingAs($this->apSupervisor);
    expect(chipCounts(Livewire::test(ListRfpApprovals::class)))->toBe(['overdue' => 0, 'due_today' => 0, 'urgent' => 0, 'waiting' => 1]);

    $foreign = queuedRfp(RfpStatus::ForAccountingApproval, RoleName::ApManager);
    Item::factory()->for($foreign)->create(['currency_id' => Currency::firstWhere('code', 'USD')->id]);

    $this->actingAs($this->apManager);
    Livewire::test(ListRfpApprovals::class)
        ->call('toggleQueueChip', 'foreign')
        ->assertCanSeeTableRecords([$foreign])
        ->assertCanNotSeeTableRecords([$waiting]);
});

it('shows Admin the whole pipeline, including RFPs stuck for days', function () {
    $stuck = queuedRfp(RfpStatus::ForApproval, RoleName::LineManager, changedAt: now()->subDays(6)->toDateTimeString());
    $moving = queuedRfp(RfpStatus::ForApproval, RoleName::LineManager);

    $this->actingAs(User::firstWhere('email', RoleAndUserSeeder::OWNER_EMAIL));

    Livewire::test(ListRfpApprovals::class)
        ->call('toggleQueueChip', 'stuck')
        ->assertSet('activeTab', 'all')
        ->assertCanSeeTableRecords([$stuck])
        ->assertCanNotSeeTableRecords([$moving]);
});

it('shows the requestor returned, draft, in progress and paid this month', function () {
    queuedRfp(RfpStatus::ForRevision, null);
    queuedRfp(RfpStatus::ForApproval, RoleName::LineManager, attributes: ['total_php_amount' => 1500]);
    $paid = queuedRfp(RfpStatus::Completed, null);
    $paidLastMonth = queuedRfp(RfpStatus::Completed, null, changedAt: now()->subMonthNoOverflow()->startOfMonth()->toDateTimeString());

    $this->actingAs($this->requestor);

    $page = Livewire::test(ListRfps::class);

    expect(chipCounts($page))->toBe(['returned' => 1, 'drafts' => 0, 'in_progress' => 1, 'paid_this_month' => 1]);

    $page->call('toggleQueueChip', 'returned')
        ->assertSet('activeTab', 'for_revision')
        ->assertSet('focus', null)
        ->call('toggleQueueChip', 'paid_this_month')
        ->assertSet('activeTab', 'closed')
        ->assertCanSeeTableRecords([$paid])
        ->assertCanNotSeeTableRecords([$paidLastMonth]);
});

it('marks an RFP overdue or due today only while it is being processed', function () {
    expect(queuedRfp(RfpStatus::ForApReview, RoleName::ApProcessor, today()->subDay()->toDateString())->dueState())->toBe('overdue')
        ->and(queuedRfp(RfpStatus::ForApReview, RoleName::ApProcessor, today()->toDateString())->dueState())->toBe('today')
        ->and(queuedRfp(RfpStatus::Completed, null, today()->subDay()->toDateString())->dueState())->toBeNull()
        ->and(queuedRfp(RfpStatus::ForApReview, RoleName::ApProcessor)->dueState())->toBeNull();
});
