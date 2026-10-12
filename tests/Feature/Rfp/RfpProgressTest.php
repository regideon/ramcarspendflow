<?php

use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Resources\Rfps\Pages\CreateRfp;
use App\Filament\Resources\Rfps\Widgets\RfpProgress;
use App\Models\Company;
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

    $this->requestor = User::firstWhere('email', 'requestor@ramcar.com');
    $this->actingAs($this->requestor);
});

function progressRfp(RfpStatus $status, array $attributes = []): Rfp
{
    $rfp = Rfp::factory()->for(test()->requestor, 'requestor')->for(Company::firstWhere('code', '1000'))->create();
    $rfp->forceFill(['status' => $status, ...$attributes])->saveQuietly();

    return $rfp;
}

function progressStates(?Rfp $rfp): array
{
    $widget = new RfpProgress;
    $widget->record = $rfp;

    return array_column($widget->getSteps(), 'state', 'label');
}

it('shows the progress stepper on the create page', function () {
    Livewire::test(CreateRfp::class)->assertSeeLivewire(RfpProgress::class);

    Livewire::test(RfpProgress::class)
        ->assertSeeInOrder(['New request', 'AP review', 'Approval', 'AP processing', 'Accounting approval', 'Treasury', 'Completed']);

    expect(progressStates(null))->toBe([
        'New request' => 'current', 'AP review' => 'upcoming', 'Approval' => 'upcoming', 'AP processing' => 'upcoming',
        'Accounting approval' => 'upcoming', 'Treasury' => 'upcoming', 'Completed' => 'upcoming',
    ]);
});

it('marks earlier stages done and names who has the RFP now', function () {
    $rfp = progressRfp(RfpStatus::ForApproval, ['current_approver_role' => RoleName::LineManager]);

    expect(progressStates($rfp))->toMatchArray(['New request' => 'done', 'AP review' => 'done', 'Approval' => 'current', 'AP processing' => 'upcoming']);

    Livewire::test(RfpProgress::class, ['record' => $rfp])->assertSee($rfp->currentApproverLabel());
});

it('shows the approval stage as skipped for a pre-approved RFP', function () {
    expect(progressStates(progressRfp(RfpStatus::ForApProcessing, ['is_pre_approved' => true])))
        ->toMatchArray(['Approval' => 'skipped', 'AP processing' => 'current']);
});

it('puts an RFP sent back for revision at the first stage', function () {
    expect(progressStates(progressRfp(RfpStatus::ForRevision)))->toMatchArray(['New request' => 'revision', 'AP review' => 'upcoming']);
});

it('marks the stage where an RFP was rejected', function () {
    $rfp = progressRfp(RfpStatus::Rejected);
    $rfp->histories()->create(['from_status' => RfpStatus::ForApproval, 'to_status' => RfpStatus::Rejected]);

    expect(progressStates($rfp))->toMatchArray(['AP review' => 'done', 'Approval' => 'failed', 'AP processing' => 'upcoming']);
});

it('marks every stage done once completed', function () {
    expect(array_unique(progressStates(progressRfp(RfpStatus::Completed))))->toBe(['New request' => 'done']);
});
