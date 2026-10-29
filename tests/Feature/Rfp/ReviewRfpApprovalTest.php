<?php

use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Pages\Rfp\Approvals;
use App\Filament\Resources\RfpApprovals\Pages\ReviewRfpApproval;
use App\Filament\Resources\RfpApprovals\Pages\ViewRfpApproval;
use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Filament\Resources\RfpApprovals\RfpApprovalReviewResource;
use App\Models\Company;
use App\Models\Rfp\Item;
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
    $this->seed([
        CompanySeeder::class, CurrencySeeder::class, AccountGroupSeeder::class, DemoMasterDataSeeder::class,
        RoleAndUserSeeder::class, DueTypeSeeder::class, ApprovalMatrixSeeder::class, WorkflowSeeder::class,
    ]);

    $this->requestor = User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::Requestor));
    $this->apProcessor = User::firstWhere('email', RoleAndUserSeeder::emailFor(RoleName::ApProcessor));
});

function rfpForReview(): Rfp
{
    $rfp = Rfp::factory()->for(test()->requestor, 'requestor')->for(Company::firstWhere('code', '1000'))->create(['purpose' => 'Globe bill for October']);
    Item::factory()->for($rfp)->create(['amount' => 50_000, 'description' => 'Globe landline and broadband']);
    $rfp->submit(test()->requestor);

    return $rfp->refresh();
}

it('shows the RFP with every guide stop, and the new queue links to it', function () {
    $rfp = rfpForReview();

    expect(RfpApprovalReviewResource::getUrl('view', ['record' => $rfp]))->toEndWith("/hub/rfp/approvals/{$rfp->getRouteKey()}");

    $response = $this->actingAs($this->apProcessor)
        ->get(RfpApprovalReviewResource::getUrl('view', ['record' => $rfp]))
        ->assertOk()
        ->assertSee($rfp->rfp_number)
        ->assertSee('Globe bill for October')
        ->assertSee('Globe landline and broadband')
        ->assertSee('Currently with:')
        ->assertSee('Endorse');

    foreach ((new ReviewRfpApproval)->guideSteps() as $step) {
        $response->assertSee('data-tour="'.$step['target'].'"', escape: false);
    }

    $this->get(Approvals::getUrl())->assertSee(RfpApprovalReviewResource::getUrl('view', ['record' => $rfp]), escape: false);
});

it('takes the step action and returns to the new queue', function () {
    $rfp = rfpForReview();

    $this->actingAs($this->apProcessor);

    Livewire::test(ReviewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->callAction('endorse', ['remarks' => 'Documents complete'])
        ->assertHasNoActionErrors()
        ->assertRedirect(Approvals::getUrl());

    expect($rfp->refresh()->status)->toBe(RfpStatus::ForApProcessing);
});

it('keeps the old screen returning to the old list', function () {
    $rfp = rfpForReview();

    $this->actingAs($this->apProcessor);

    Livewire::test(ViewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->callAction('endorse')
        ->assertRedirect(RfpApprovalResource::getUrl());
});

it('posts a comment from the comments card', function () {
    $rfp = rfpForReview();

    $this->actingAs($this->apProcessor);

    Livewire::test(ReviewRfpApproval::class, ['record' => $rfp->getRouteKey()])
        ->callAction('addComment', ['body' => '<p>Please attach the official receipt.</p>'])
        ->assertHasNoActionErrors()
        ->assertSee('Please attach the official receipt.');
});
