<?php

namespace App\Filament\KnowledgeBase;

/**
 * One knowledge-base article, loaded from resources/knowledge-base/{slug}.md.
 */
final readonly class Article
{
    public function __construct(
        public string $slug,
        public string $title,
        public string $summary,
        public string $category,
        public int $order,
        public string $markdown,
    ) {}

    public function matches(string $search): bool
    {
        $search = mb_strtolower(trim($search));

        return $search === ''
            || str_contains(mb_strtolower($this->title), $search)
            || str_contains(mb_strtolower($this->summary), $search)
            || str_contains(mb_strtolower($this->markdown), $search);
    }
}
