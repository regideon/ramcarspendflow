<?php

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Resources\P2p\Approvals\Pages\ListApprovals;
use App\Filament\Resources\P2p\Invoices\Pages\ListInvoices;
use App\Livewire\CommentsChat;
use App\Models\Comment;
use App\Models\Company;
use App\Models\P2p\Invoice;
use App\Models\User;
use Database\Seeders\RoleAndUserSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndUserSeeder::class);

    $this->vendor = User::factory()->create()->assignRole(RoleName::Vendor);
    $this->otherVendor = User::factory()->create()->assignRole(RoleName::Vendor);
    $this->ap = User::factory()->create()->assignRole(RoleName::ApProcessor);

    $this->invoice = Invoice::factory()->for($this->vendor)->create([
        'status' => P2pStatus::SubmittedToAp,
        'company_id' => Company::factory(),
        'submitted_at' => now()->subDay(),
    ]);
});

function mentionOf(User $user): string
{
    return sprintf('<span data-type="mention" data-id="%d" data-char="@">@%s</span>', $user->id, e($user->name));
}

it('defaults a staff message to internal and hides it from the vendor', function () {
    $this->actingAs($this->ap);

    Livewire::test(CommentsChat::class, ['record' => $this->invoice])
        ->call('toggle')
        ->assertFormFieldVisible('is_internal')
        ->fillForm(['body' => '<p>Check the GR qty, '.mentionOf($this->vendor).'</p>'])
        ->call('send')
        ->assertHasNoFormErrors();

    expect(Comment::sole()->is_internal)->toBeTrue()
        ->and($this->vendor->notifications()->count())->toBe(0)
        ->and($this->invoice->unreadCommentsCountFor($this->vendor))->toBe(0);

    $this->actingAs($this->vendor);

    Livewire::test(CommentsChat::class, ['record' => $this->invoice])
        ->call('toggle')
        ->assertViewHas('comments', fn ($comments) => $comments->isEmpty())
        ->assertDontSee('Check the GR qty');
});

it('shows a vendor-visible message to the vendor and notifies them', function () {
    $this->actingAs($this->ap);

    Livewire::test(CommentsChat::class, ['record' => $this->invoice])
        ->call('toggle')
        ->fillForm(['body' => '<p>Please send the DR, '.mentionOf($this->vendor).'</p>', 'is_internal' => false])
        ->call('send');

    expect($this->vendor->notifications()->count())->toBe(1)
        ->and($this->invoice->unreadCommentsCountFor($this->vendor))->toBe(1);

    $this->actingAs($this->vendor);

    Livewire::test(CommentsChat::class)
        ->assertViewHas('unread', 1)
        ->call('toggle')
        ->assertSee('Please send the DR');
});

it('never lets a vendor post an internal message', function () {
    $this->actingAs($this->vendor);

    Livewire::test(CommentsChat::class, ['record' => $this->invoice])
        ->call('toggle')
        ->assertFormFieldHidden('is_internal')
        ->fillForm(['body' => '<p>Sent it</p>', 'is_internal' => true])
        ->call('send');

    expect(Comment::sole()->is_internal)->toBeFalse();
});

it('keeps other vendors out of the thread', function () {
    $this->actingAs($this->otherVendor);

    Livewire::test(CommentsChat::class, ['record' => $this->invoice])
        ->assertSet('type', null)
        ->call('openThread', 'p2p_invoice', $this->invoice->id)
        ->assertForbidden();
});

it('shows the bubble and row chat icons on the P2P lists', function () {
    $this->actingAs($this->ap);

    Livewire::test(ListApprovals::class)
        ->assertSeeLivewire(CommentsChat::class)
        ->assertActionVisible(TestAction::make('comments')->table($this->invoice));

    $this->actingAs($this->vendor);

    Livewire::test(ListInvoices::class)
        ->assertSeeLivewire(CommentsChat::class)
        ->assertActionVisible(TestAction::make('comments')->table($this->invoice));
});

it('opens the invoice thread on the AP processing screen', function () {
    Comment::factory()->for($this->invoice, 'commentable')->for($this->vendor)->create(['body' => '<p>Any update?</p>']);

    $this->actingAs($this->ap)
        ->get('/hub/p2p/bulk-accounts-payable?invoice='.$this->invoice->id)
        ->assertOk()
        ->assertSeeLivewire(CommentsChat::class);

    Livewire::test(CommentsChat::class, ['record' => $this->invoice])
        ->assertSet('type', 'p2p_invoice')
        ->assertViewHas('unread', 1);
});
