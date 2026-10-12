<?php

namespace App\Filament\Pages;

use App\Filament\KnowledgeBase\Article;
use App\Filament\KnowledgeBase\ArticleRepository;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * In-app help: explains how the system works, one article at a time.
 * Open to every signed-in user. Add articles as Markdown in resources/knowledge-base.
 */
class KnowledgeBase extends Page
{
    protected string $view = 'filament.pages.knowledge-base';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    // protected static string|UnitEnum|null $navigationGroup = 'Help';
    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Knowledge Base';

    protected static ?string $title = 'Knowledge Base';

    protected static ?string $slug = 'knowledge-base';

    protected static ?int $navigationSort = 200;

    #[Url]
    public ?string $article = null;

    public string $search = '';

    /**
     * @return Collection<string, Collection<int, Article>>
     */
    #[Computed]
    public function articlesByCategory(): Collection
    {
        return app(ArticleRepository::class)->all()
            ->filter(fn (Article $article): bool => $article->matches($this->search))
            ->groupBy('category');
    }

    #[Computed]
    public function currentArticle(): ?Article
    {
        $repository = app(ArticleRepository::class);

        return $repository->find($this->article) ?? $repository->all()->first();
    }

    /**
     * @return array{html: string, headings: list<array{id: string, title: string}>}|null
     */
    #[Computed]
    public function rendered(): ?array
    {
        return $this->currentArticle ? app(ArticleRepository::class)->render($this->currentArticle) : null;
    }

    public function selectArticle(string $slug): void
    {
        $this->article = $slug;

        unset($this->currentArticle, $this->rendered);
    }

    public function getHeading(): string
    {
        return '';
    }
}
