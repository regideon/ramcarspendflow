<?php

use App\Enums\RfpStatus;
use App\Filament\Pages\Rfp\MyRequests;
use App\Models\Company;
use App\Models\Rfp\Rfp;
use App\Models\User;
use Database\Seeders\CompanySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\Rfp\DueTypeSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([CompanySeeder::class, CurrencySeeder::class, RoleAndUserSeeder::class, DueTypeSeeder::class]);

    $this->requestor = User::firstWhere('email', 'requestor@ramcar.com');
    $this->company = Company::firstWhere('code', '1000');
});

function myRfp(RfpStatus $status, array $attributes = []): Rfp
{
    // Set after create: creating an RFP derives its own due date.
    $rfp = Rfp::factory()->for(test()->requestor, 'requestor')->for(test()->company)->create();
    $rfp->forceFill(['status' => $status, ...$attributes])->saveQuietly();

    return $rfp;
}

it('lists only the requestor\'s own requests, with every guide stop on the page', function () {
    $mine = myRfp(RfpStatus::ForApReview);
    $someoneElses = Rfp::factory()->for($this->company)->create();

    $response = $this->actingAs($this->requestor)
        ->get(MyRequests::getUrl())
        ->assertOk()
        ->assertSee('Requestor')
        ->assertSee($mine->rfp_number)
        ->assertDontSee($someoneElses->rfp_number);

    foreach ((new MyRequests)->guideSteps() as $step) {
        $response->assertSee('data-tour="'.$step['target'].'"', escape: false);
    }
});

it('filters by tab, summary chip and search', function () {
    $draft = myRfp(RfpStatus::Draft);
    $returned = myRfp(RfpStatus::ForRevision);
    $overdue = myRfp(RfpStatus::ForApproval, ['due_date' => today()->subDays(2)]);

    $this->actingAs($this->requestor);

    Livewire::test(MyRequests::class)
        ->call('setTab', 'drafts')
        ->assertSee($draft->rfp_number)
        ->assertDontSee($returned->rfp_number)
        ->call('pickFocus', 'overdue')
        ->assertSet('tab', 'in_progress')
        ->assertSee($overdue->rfp_number)
        ->assertDontSee($draft->rfp_number)
        ->call('clearFilters')
        ->call('setTab', 'all')
        ->set('search', $returned->rfp_number)
        ->assertSee($returned->rfp_number)
        ->assertDontSee($draft->rfp_number);
});

it('previews a request in the drawer', function () {
    $rfp = myRfp(RfpStatus::ForRevision, ['purpose' => 'Plant electricity for October']);

    $this->actingAs($this->requestor);

    Livewire::test(MyRequests::class)
        ->call('openPreview', $rfp->id)
        ->assertSee('Plant electricity for October')
        ->assertSee('Fix what was asked and resubmit.')
        ->call('closePreview')
        ->assertSet('previewId', null);
});

it('deletes only selected drafts', function () {
    $draft = myRfp(RfpStatus::Draft);
    $submitted = myRfp(RfpStatus::ForApReview);

    $this->actingAs($this->requestor);

    Livewire::test(MyRequests::class)
        ->set('selected', [(string) $draft->id, (string) $submitted->id])
        ->call('deleteSelectedDrafts')
        ->assertSet('selected', []);

    expect(Rfp::find($draft->id))->toBeNull()
        ->and(Rfp::find($submitted->id))->not->toBeNull();
});

it('exports the list as CSV', function () {
    myRfp(RfpStatus::ForApReview);

    $this->actingAs($this->requestor);

    Livewire::test(MyRequests::class)
        ->call('export')
        ->assertFileDownloaded('my-requests.csv');
});

it('is not open to roles that do not raise requests', function () {
    $this->actingAs(User::firstWhere('email', 'treasury@ramcar.com'))
        ->get(MyRequests::getUrl())
        ->assertForbidden();
});
