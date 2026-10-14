<?php

use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Resources\RfpApprovals\Pages\ViewRfpApproval;
use App\Filament\Resources\Rfps\Pages\ViewRfp;
use App\Models\Comment;
use App\Models\Company;
use App\Models\Rfp\Rfp;
use App\Models\User;
use Database\Seeders\AccountGroupSeeder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\DemoMasterDataSeeder;
use Database\Seeders\Rfp\DueTypeSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([CompanySeeder::class, CurrencySeeder::class, AccountGroupSeeder::class, DemoMasterDataSeeder::class, RoleAndUserSeeder::class, DueTypeSeeder::class]);

    $this->requestor = User::firstWhere('email', 'requestor@ramcar.com');
    $this->apProcessor = User::firstWhere('email', 'ap.processor@ramcar.com');
    $this->lineManager = User::firstWhere('email', 'line.manager@ramcar.com');

    $this->rfp = Rfp::factory()->for($this->requestor, 'requestor')->for(Company::firstWhere('code', '1000'))->create();
    $this->rfp->forceFill(['status' => RfpStatus::ForApReview, 'current_approver_role' => RoleName::ApProcessor])->saveQuietly();
});

function mention(User $user): string
{
    return sprintf('<span data-type="mention" data-id="%d" data-char="@">@%s</span>', $user->id, e($user->name));
}

function commentAction(): TestAction
{
    return TestAction::make('addComment')->schemaComponent('comments', 'infolist');
}

it('lets AP comment and notifies the mentioned requestor', function () {
    $this->actingAs($this->apProcessor);

    Livewire::test(ViewRfpApproval::class, ['record' => $this->rfp->getRouteKey()])
        ->callAction(commentAction(), ['body' => '<p>Please attach the OR, '.mention($this->requestor).'</p>'])
        ->assertHasNoFormErrors()
        ->assertSee('Please attach the OR');

    $comment = Comment::sole();

    expect($comment->user_id)->toBe($this->apProcessor->id)
        ->and($comment->commentable_type)->toBe('rfp')
        ->and($comment->commentable->is($this->rfp))->toBeTrue()
        ->and($comment->mentionedUserIds())->toBe([$this->requestor->id])
        ->and($this->rfp->fresh()->status)->toBe(RfpStatus::ForApReview)
        ->and($this->requestor->notifications()->count())->toBe(1)
        ->and($this->requestor->notifications()->first()->data['title'])->toContain('mentioned you on '.$this->rfp->rfp_number);
});

it('lets the requestor reply from My Requests', function () {
    $this->actingAs($this->requestor);

    Livewire::test(ViewRfp::class, ['record' => $this->rfp->getRouteKey()])
        ->callAction(commentAction(), ['body' => '<p>Attached, '.mention($this->apProcessor).'</p>'])
        ->assertHasNoFormErrors();

    expect($this->apProcessor->notifications()->count())->toBe(1);
});

it('only notifies people who can open the RFP, and never the author', function () {
    $this->actingAs($this->apProcessor);

    Livewire::test(ViewRfpApproval::class, ['record' => $this->rfp->getRouteKey()])
        ->callAction(commentAction(), ['body' => '<p>'.mention($this->lineManager).' '.mention($this->apProcessor).'</p>']);

    expect($this->lineManager->notifications()->count())->toBe(0)
        ->and($this->apProcessor->notifications()->count())->toBe(0);
});

it('requires a comment body', function () {
    $this->actingAs($this->apProcessor);

    Livewire::test(ViewRfpApproval::class, ['record' => $this->rfp->getRouteKey()])
        ->callAction(commentAction(), ['body' => null])
        ->assertHasFormErrors(['body']);

    expect(Comment::count())->toBe(0);
});

it('keeps comments on a soft delete and removes them on a force delete', function () {
    Comment::factory()->for($this->rfp, 'commentable')->create();

    $this->rfp->delete();
    expect(Comment::count())->toBe(1);

    $this->rfp->forceDelete();
    expect(Comment::count())->toBe(0);
});

it('counts unread messages for a whole list in one query, matching the per-record count', function () {
    $apProcessor = User::firstWhere('email', 'ap.processor@ramcar.com');
    $requestor = User::firstWhere('email', 'requestor@ramcar.com');
    $company = Company::firstWhere('code', '1000');

    $rfps = Rfp::factory()->count(3)->for($requestor, 'requestor')->for($company)->create(['status' => RfpStatus::ForApReview]);
    $rfps[0]->comments()->create(['user_id' => $requestor->id, 'body' => 'one']);
    $rfps[0]->comments()->create(['user_id' => $requestor->id, 'body' => 'two']);
    $rfps[0]->comments()->create(['user_id' => $apProcessor->id, 'body' => 'my own']);
    $rfps[1]->comments()->create(['user_id' => $requestor->id, 'body' => 'read already']);
    $rfps[1]->markCommentsReadBy($apProcessor);
    $rfps[1]->comments()->create(['user_id' => $requestor->id, 'body' => 'new']);

    $this->actingAs($apProcessor);

    $expected = $rfps->mapWithKeys(fn ($rfp): array => [$rfp->id => $rfp->fresh()->unreadCommentsCountFor($apProcessor)])->all();

    $listed = Rfp::query()->whereKey($rfps->pluck('id'))->withUnreadCommentsCountFor($apProcessor)->get();

    DB::enableQueryLog();
    $counts = $listed->mapWithKeys(fn ($rfp): array => [$rfp->id => $rfp->unreadCommentsCountFor($apProcessor)])->all();

    expect($counts)->toBe($expected)
        ->and($expected)->toBe([$rfps[0]->id => 2, $rfps[1]->id => 1, $rfps[2]->id => 0])
        ->and(DB::getQueryLog())->toBe([]);
});
