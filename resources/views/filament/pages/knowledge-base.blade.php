<x-filament-panels::page>
    <div class="kb-layout">
        {{-- Article list --}}
        <aside class="kb-sidebar">
            <x-filament::section compact>
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search articles"
                        aria-label="Search articles"
                    />
                </x-filament::input.wrapper>

                <nav class="kb-nav" aria-label="Articles">
                    @forelse ($this->articlesByCategory as $category => $articles)
                        <div class="kb-nav-group">
                            <p class="kb-nav-category">{{ $category }}</p>

                            @foreach ($articles as $item)
                                <button
                                    type="button"
                                    wire:click="selectArticle('{{ $item->slug }}')"
                                    @class(['kb-nav-item', 'kb-nav-item-active' => $this->currentArticle?->slug === $item->slug])
                                    @if ($this->currentArticle?->slug === $item->slug) aria-current="page" @endif
                                >
                                    {{ $item->title }}
                                </button>
                            @endforeach
                        </div>
                    @empty
                        <p class="kb-muted">No articles match “{{ $search }}”.</p>
                    @endforelse
                </nav>
            </x-filament::section>
        </aside>

        {{-- Article --}}
        <article class="kb-article" wire:key="article-{{ $this->currentArticle?->slug }}">
            @if ($this->currentArticle)
                <x-filament::section>
                    <header class="kb-article-header">
                        <x-filament::badge color="gray">{{ $this->currentArticle->category }}</x-filament::badge>
                        <h1 class="kb-title">{{ $this->currentArticle->title }}</h1>

                        @if ($this->currentArticle->summary)
                            <p class="kb-summary">{{ $this->currentArticle->summary }}</p>
                        @endif

                        @if (count($this->rendered['headings']) > 1)
                            <nav class="kb-toc" aria-label="On this page">
                                <span class="kb-toc-label">On this page:</span>
                                @foreach ($this->rendered['headings'] as $heading)
                                    <a href="#{{ $heading['id'] }}" class="kb-toc-link">{{ $heading['title'] }}</a>
                                @endforeach
                            </nav>
                        @endif
                    </header>

                    <div class="fi-prose kb-prose">
                        {!! $this->rendered['html'] !!}
                    </div>
                </x-filament::section>
            @else
                <x-filament::section>
                    <p class="kb-muted">No articles yet.</p>
                </x-filament::section>
            @endif
        </article>
    </div>
</x-filament-panels::page>
