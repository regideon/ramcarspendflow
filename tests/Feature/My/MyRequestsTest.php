<?php

use App\Filament\My\Pages\MyRequests;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('my');
});

it('lists the requests grouped by month', function () {
    $this->get(route('filament.my.pages.requests'))
        ->assertOk()
        ->assertSeeInOrder(['October 2026', 'Office supplies – October', 'September 2026', 'Annual AWS Cloud Reserved Instances']);
});

it('filters by status tab', function () {
    Livewire::test(MyRequests::class)
        ->call('setTab', 'for_revision')
        ->assertSee('Q4 Team Strategic Planning Venue')
        ->assertDontSee('Fleet GPS subscription');
});

it('searches by RFP number, payee or purpose', function () {
    Livewire::test(MyRequests::class)
        ->set('search', 'cartrack')
        ->assertSee('Fleet GPS subscription')
        ->assertDontSee('Warehouse forklift repair');
});

it('shows the empty state when nothing matches', function () {
    Livewire::test(MyRequests::class)
        ->set('search', 'no such request')
        ->assertSee('Nothing here');
});

it('falls back to all for an unknown tab', function () {
    Livewire::test(MyRequests::class)
        ->call('setTab', 'bogus')
        ->assertSet('tab', 'all');
});
