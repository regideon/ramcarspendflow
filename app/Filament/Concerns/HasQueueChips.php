<?php

namespace App\Filament\Concerns;

use App\Services\Rfp\QueueChip;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Livewire\Attributes\Url;

/**
 * Urgency chips above a resource list's tabs. Picking a chip opens its tab and narrows the table;
 * picking it again (or another tab) clears it. The chip is kept in the URL (?focus=overdue).
 */
trait HasQueueChips
{
    #[Url]
    public ?string $focus = null;

    /**
     * @return list<QueueChip>
     */
    abstract protected function queueChips(): array;

    public function content(Schema $schema): Schema
    {
        $schema = parent::content($schema);

        return $schema->components([
            View::make('filament.rfps.queue-chips')->viewData(fn (): array => ['chips' => $this->queueChipStates()]),
            ...$schema->getComponents(),
        ]);
    }

    public function toggleQueueChip(string $key): void
    {
        $chip = $this->queueChip($key);

        if (! $chip) {
            return;
        }

        $this->activeTab = $chip->tab;
        $this->focus = ($this->focus === $key || ! $chip->filters()) ? null : $key;
        $this->updatedActiveTab();
    }

    public function updatedActiveTab(): void
    {
        // A tab clicked by hand clears the chip; toggleQueueChip() sets it again afterwards.
        if (($chip = $this->queueChip($this->focus)) && $chip->tab !== $this->activeTab) {
            $this->focus = null;
        }

        parent::updatedActiveTab();
    }

    protected function modifyQueryWithActiveTab(Builder $query, bool $isResolvingRecord = false): Builder
    {
        $query = parent::modifyQueryWithActiveTab($query, $isResolvingRecord);

        return $this->queueChip($this->focus)?->apply($query) ?? $query;
    }

    protected function queueChip(?string $key): ?QueueChip
    {
        return $key ? Arr::first($this->queueChips(), fn (QueueChip $chip): bool => $chip->key === $key) : null;
    }

    /**
     * @return list<array{chip: QueueChip, count: int, amount: ?float, active: bool}>
     */
    protected function queueChipStates(): array
    {
        $tabs = $this->getCachedTabs();

        return array_map(function (QueueChip $chip) use ($tabs): array {
            $query = static::getResource()::getEloquentQuery();
            $query = $chip->apply(isset($tabs[$chip->tab]) ? $tabs[$chip->tab]->modifyQuery($query) : $query);

            return [
                'chip' => $chip,
                'count' => (clone $query)->count(),
                'amount' => $chip->showsAmount ? (float) $query->sum('total_php_amount') : null,
                'active' => $this->focus === $chip->key || (! $chip->filters() && $this->focus === null && $this->activeTab === $chip->tab),
            ];
        }, $this->queueChips());
    }
}
