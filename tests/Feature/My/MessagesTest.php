<?php

use App\Livewire\CommentsChat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('shows the chat bubble instead of a Messages tab', function () {
    $this->get(route('filament.my.pages.dashboard'))
        ->assertOk()
        ->assertSeeLivewire(CommentsChat::class)
        ->assertDontSee('aria-label="Messages" class="relative flex size-10', escape: false);
});

it('hides the chat bubble while creating an RFP', function () {
    $this->get(route('filament.my.pages.requests.new'))
        ->assertOk()
        ->assertDontSeeLivewire(CommentsChat::class);
});

it('opens the inbox from a Messages button', function () {
    Livewire::test(CommentsChat::class)
        ->assertSet('open', false)
        ->dispatch('open-comments-inbox')
        ->assertSet('open', true)
        ->assertSee('No conversations yet');
});
