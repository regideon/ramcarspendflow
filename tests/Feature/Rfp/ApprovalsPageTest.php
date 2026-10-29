<?php

use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Pages\Rfp\Approvals;
use App\Models\Company;
use App\Models\Rfp\Rfp;
use App\Models\User;
use Database\Seeders\AccountGroupSeeder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\DemoMasterDataSeeder;
use Database\Seeders\Rfp\ApprovalMatrixSeeder;
use Database\Seeders\Rfp\DueTypeSeeder;
use Database\Seeders\Rfp\WorkflowSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([CompanySeeder::class, CurrencySeeder::class, AccountGroupSeeder::class, DemoMasterDataSeeder::class, RoleAndUserSeeder::class, DueTypeSeeder::class, ApprovalMatrixSeeder::class, WorkflowSeeder::class]);

    $this->company = Company::firstWhere('code', '1000');
    $this->requestor = User::firstWhere('email', 'requestor@ramcar.com');
    $this->apProcessor = User::firstWhere('email', 'ap.processor@ramcar.com');
});

function approvalsPageRfp(RfpStatus $status, array $attributes = []): Rfp
{
    // Set after create: creating an RFP derives its own due date.
    $rfp = Rfp::factory()->for(test()->requestor, 'requestor')->for(test()->company)->create();
    $rfp->forceFill(['status' => $status, ...$attributes])->saveQuietly();

    return $rfp;
}

it('is only for Admin, AP and Treasury', function (string $email, int $status) {
    $this->actingAs(User::firstWhere('email', $email))->get(Approvals::getUrl())->assertStatus($status);
})->with([
    'Admin' => [RoleAndUserSeeder::OWNER_EMAIL, 200],
    'AP Processor' => ['ap.processor@ramcar.com', 200],
    'Treasury' => ['treasury@ramcar.com', 200],
    'Requestor' => ['requestor@ramcar.com', 403],
]);

it('opens on For my action, with every guide stop on the page', function () {
    $mine = approvalsPageRfp(RfpStatus::ForApReview, ['current_approver_role' => RoleName::ApProcessor]);
    $notMine = approvalsPageRfp(RfpStatus::ForTreasury, ['current_approver_role' => RoleName::Treasury]);

    $response = $this->actingAs($this->apProcessor)
        ->get(Approvals::getUrl())
        ->assertOk()
        ->assertSee($mine->rfp_number)
        ->assertDontSee($notMine->rfp_number);

    foreach ((new Approvals)->guideSteps() as $step) {
        $response->assertSee('data-tour="'.$step['target'].'"', escape: false);
    }
});

it('filters by route tab, urgency chip, filter and search', function () {
    $review = approvalsPageRfp(RfpStatus::ForApReview, ['current_approver_role' => RoleName::ApProcessor, 'due_date' => today()->subDays(2)]);
    $treasury = approvalsPageRfp(RfpStatus::ForTreasury, ['current_approver_role' => RoleName::Treasury]);
    $paid = approvalsPageRfp(RfpStatus::Completed);

    $this->actingAs($this->apProcessor);

    Livewire::test(Approvals::class)
        ->call('setTab', 'for_treasury')
        ->assertSee($treasury->rfp_number)
        ->assertDontSee($review->rfp_number)
        ->call('pickFocus', 'overdue')
        ->assertSet('tab', 'mine')
        ->assertSee($review->rfp_number)
        ->assertDontSee($treasury->rfp_number)
        ->call('setTab', 'all')
        ->assertSet('focus', null)
        ->set('statuses', [RfpStatus::Completed->value])
        ->assertSee($paid->rfp_number)
        ->assertDontSee($review->rfp_number)
        ->assertSee('Active filters:')
        ->call('removeFilter', 'statuses')
        ->set('search', $treasury->rfp_number)
        ->assertSee($treasury->rfp_number)
        ->assertDontSee($paid->rfp_number);
});
