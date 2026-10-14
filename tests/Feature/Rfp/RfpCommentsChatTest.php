<?php

use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Resources\RfpApprovals\Pages\ListRfpApprovals;
use App\Filament\Resources\RfpApprovals\Pages\ViewRfpApproval;
use App\Filament\Resources\Rfps\Pages\CreateRfp;
use App\Filament\Resources\Rfps\Pages\EditRfp;
use App\Filament\Resources\Rfps\Pages\ListRfps;
use App\Filament\Resources\Rfps\Pages\ViewRfp;
use App\Livewire\CommentsChat;
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

it('shows the chat bubble on both RFP pages', function () {
    $this->actingAs($this->apProcessor);
    Livewire::test(ViewRfpApproval::class, ['record' => $this->rfp->getRouteKey()])
        ->assertSeeLivewire(CommentsChat::class);

    $this->actingAs($this->requestor);
    Livewire::test(ViewRfp::class, ['record' => $this->rfp->getRouteKey()])
        ->assertSeeLivewire(CommentsChat::class);
});

it('sends a message, notifies the mentioned user and refreshes the page', function () {
    $this->actingAs($this->apProcessor);

    $body = sprintf('<p>Please attach the OR, <span data-type="mention" data-id="%d" data-char="@">@%s</span></p>', $this->requestor->id, e($this->requestor->name));

    Livewire::test(CommentsChat::class, ['record' => $this->rfp])
        ->call('toggle')
        ->fillForm(['body' => $body])
        ->call('send')
        ->assertHasNoFormErrors()
        ->assertDispatched('comment-posted')
        ->assertSee('Please attach the OR');

    $comment = Comment::sole();

    expect($comment->commentable->is($this->rfp))->toBeTrue()
        ->and($comment->user_id)->toBe($this->apProcessor->id)
        ->and($this->requestor->notifications()->count())->toBe(1);
});

it('requires a message', function () {
    $this->actingAs($this->apProcessor);

    Livewire::test(CommentsChat::class, ['record' => $this->rfp])
        ->call('toggle')
        ->fillForm(['body' => null])
        ->call('send')
        ->assertHasFormErrors(['body']);

    expect(Comment::count())->toBe(0);
});

it('counts unread messages from others until the chat is opened', function () {
    Comment::factory()->for($this->rfp, 'commentable')->for($this->apProcessor)->count(2)->create();
    Comment::factory()->for($this->rfp, 'commentable')->for($this->requestor)->create();

    $this->actingAs($this->requestor);

    Livewire::test(CommentsChat::class, ['record' => $this->rfp])
        ->assertViewHas('unread', 2)
        ->call('toggle')
        ->assertViewHas('comments', fn ($comments) => $comments->count() === 3)
        ->call('toggle')
        ->assertViewHas('unread', 0);

    Comment::factory()->for($this->rfp, 'commentable')->for($this->apProcessor)->create();

    expect($this->rfp->unreadCommentsCountFor($this->requestor))->toBe(1)
        ->and($this->rfp->unreadCommentsCountFor($this->apProcessor))->toBe(1); // only the requestor's message; own messages never count
});

it('does not open the thread for people who cannot open the RFP', function () {
    $this->actingAs($this->lineManager);

    Livewire::test(CommentsChat::class, ['record' => $this->rfp])
        ->assertSet('type', null)
        ->call('openThread', 'rfp', $this->rfp->id)
        ->assertForbidden();
});

it('shows the bubble as an inbox on the RFP lists and create page, and the thread on edit', function () {
    $this->actingAs($this->requestor);

    Livewire::test(ListRfps::class)->assertSeeLivewire(CommentsChat::class);
    Livewire::test(CreateRfp::class)->assertSeeLivewire(CommentsChat::class);

    $this->actingAs($this->apProcessor);
    Livewire::test(ListRfpApprovals::class)->assertSeeLivewire(CommentsChat::class);
});

it('lists conversations in the inbox with unread counts and opens one', function () {
    Comment::factory()->for($this->rfp, 'commentable')->for($this->apProcessor)->create(['body' => '<p>Need the OR</p>']);
    $quiet = Rfp::factory()->for($this->requestor, 'requestor')->for(Company::firstWhere('code', '1000'))->create();

    $this->actingAs($this->requestor);

    Livewire::test(CommentsChat::class)
        ->assertViewHas('unread', 1)
        ->call('toggle')
        ->assertViewHas('threads', fn ($threads) => $threads->count() === 1
            && $threads->first()->is($this->rfp)
            && $threads->first()->unread_comments_count === 1)
        ->assertSee('Need the OR')
        ->call('openThread', 'rfp', $this->rfp->id)
        ->assertSet('type', 'rfp')
        ->assertViewHas('comments', fn ($comments) => $comments->count() === 1)
        ->call('showInbox')
        ->assertViewHas('threads', fn ($threads) => $threads->first()->unread_comments_count === 0);

    expect($quiet->unreadCommentsCountFor($this->requestor))->toBe(0);
});

it('adds a chat icon with the unread count to each RFP row', function () {
    Comment::factory()->for($this->rfp, 'commentable')->for($this->apProcessor)->create();

    $this->actingAs($this->requestor);

    Livewire::test(ListRfps::class)
        ->assertActionVisible(TestAction::make('comments')->table($this->rfp));
});

it('opens the RFP thread from the edit page', function () {
    $this->rfp->forceFill(['status' => RfpStatus::Draft, 'current_approver_role' => null])->saveQuietly();

    $this->actingAs($this->requestor);

    Livewire::test(EditRfp::class, ['record' => $this->rfp->getRouteKey()])
        ->assertSeeLivewire(CommentsChat::class);

    Livewire::test(CommentsChat::class, ['record' => $this->rfp])
        ->assertSet('type', 'rfp');
});
