<?php

namespace App\Filament\KnowledgeBase;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Loads knowledge-base articles and renders them to HTML.
 *
 * Each article is a Markdown file with a small header:
 *
 *   ---
 *   title: Approval flow
 *   summary: One line shown under the title
 *   category: Approvals
 *   order: 1
 *   ---
 *
 * A paragraph containing only {{block:name}} is replaced with the live Blade view
 * resources/views/filament/knowledge-base/blocks/{name}.blade.php, so data such as
 * the approval matrix always reflects the current settings.
 */
class ArticleRepository
{
    public function __construct(protected ?string $path = null)
    {
        $this->path ??= resource_path('knowledge-base');
    }

    /**
     * @return Collection<int, Article>
     */
    public function all(): Collection
    {
        return collect(File::glob("{$this->path}/*.md"))
            ->map(fn (string $file): Article => $this->parse($file))
            ->sortBy([['category', 'asc'], ['order', 'asc'], ['title', 'asc']])
            ->values();
    }

    public function find(?string $slug): ?Article
    {
        return $this->all()->firstWhere('slug', $slug);
    }

    /**
     * @return array{html: string, headings: list<array{id: string, title: string}>}
     */
    public function render(Article $article): array
    {
        $html = Str::markdown($article->markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $headings = [];

        $html = preg_replace_callback('/<h2>(.*?)<\/h2>/s', function (array $match) use (&$headings): string {
            $id = Str::slug(strip_tags($match[1]));
            $headings[] = ['id' => $id, 'title' => html_entity_decode(strip_tags($match[1]))];

            return "<h2 id=\"{$id}\">{$match[1]}</h2>";
        }, $html);

        $html = preg_replace_callback('/<p>\{\{block:([a-z0-9-]+)\}\}<\/p>/', function (array $match): string {
            $view = "filament.knowledge-base.blocks.{$match[1]}";

            return view()->exists($view) ? view($view)->render() : '';
        }, $html);

        return ['html' => $html, 'headings' => $headings];
    }

    protected function parse(string $file): Article
    {
        $contents = File::get($file);
        $meta = [];

        if (preg_match('/\A---\R(.*?)\R---\R/s', $contents, $match)) {
            foreach (preg_split('/\R/', $match[1]) as $line) {
                if (str_contains($line, ':')) {
                    [$key, $value] = array_map('trim', explode(':', $line, 2));
                    $meta[$key] = $value;
                }
            }

            $contents = substr($contents, strlen($match[0]));
        }

        $slug = pathinfo($file, PATHINFO_FILENAME);

        return new Article(
            slug: $slug,
            title: $meta['title'] ?? Str::headline($slug),
            summary: $meta['summary'] ?? '',
            category: $meta['category'] ?? 'General',
            order: (int) ($meta['order'] ?? 100),
            markdown: trim($contents),
        );
    }
}
