<?php

use App\Enums\RoleName;
use App\Filament\KnowledgeBase\ArticleRepository;
use App\Filament\Pages\KnowledgeBase;
use App\Models\Rfp\ApprovalRule;
use App\Models\User;
use Database\Seeders\Rfp\ApprovalMatrixSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RoleAndUserSeeder::class, ApprovalMatrixSeeder::class]);
});

it('is open to every role', function (RoleName $roleName) {
    $this->actingAs(User::firstWhere('email', RoleAndUserSeeder::emailFor($roleName)))
        ->get('/hub/knowledge-base')
        ->assertOk()
        ->assertSee('RFP approval flow');
})->with([RoleName::Requestor, RoleName::Vendor, RoleName::LineManager, RoleName::ApProcessor, RoleName::Treasury]);

it('shows the live approval matrix from the database', function () {
    $this->actingAs(User::firstWhere('email', 'requestor@ramcar.com'))
        ->get('/hub/knowledge-base?article=approval-flow')
        ->assertOk()
        ->assertSee('Line Manager → Department Head → Division Head → Controller')
        ->assertSee('10,000.01 to 100,000.00')
        ->assertSee('Above 25,000,000.00')
        ->assertDontSee('{{block:', escape: false);
});

it('reflects matrix changes without editing the article', function () {
    ApprovalRule::query()->where('max_amount', 10_000)->update(['max_amount' => 20_000]);

    $html = app(ArticleRepository::class)->render(app(ArticleRepository::class)->find('approval-flow'))['html'];

    expect($html)->toContain('Up to 20,000.00')->toContain('20,000.01 to 100,000.00');
});

it('loads every article with a title, category and rendered headings', function () {
    $repository = app(ArticleRepository::class);

    expect($repository->all())->not->toBeEmpty();

    $repository->all()->each(function ($article) use ($repository) {
        expect($article->title)->not->toBeEmpty()
            ->and($article->category)->not->toBeEmpty()
            ->and($repository->render($article)['headings'])->not->toBeEmpty();
    });
});

it('filters the article list by search', function () {
    $this->actingAs(User::firstWhere('email', 'requestor@ramcar.com'));

    Livewire::test(KnowledgeBase::class)
        ->set('search', 'pre-approved')
        ->assertSee('RFP approval flow')
        ->set('search', 'no such topic here')
        ->assertSee('No articles match');
});

it('falls back to the first article for an unknown slug', function () {
    $this->actingAs(User::firstWhere('email', 'requestor@ramcar.com'));

    Livewire::withQueryParams(['article' => 'does-not-exist'])
        ->test(KnowledgeBase::class)
        ->assertSee('RFP approval flow');
});
