<?php

namespace App\Filament\Resources\P2p\Escalations\Pages;

use App\Filament\Resources\P2p\Escalations\EscalationResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListEscalations extends ListRecords
{
    protected static string $resource = EscalationResource::class;

    public function getTabs(): array
    {
        $base = fn (): Builder => EscalationResource::getEloquentQuery();

        return [
            'action_required' => Tab::make('Action Required')
                ->badge(fn (): int => $this->countByStep($base(), 1))
                ->query(fn (Builder $query): Builder => $query->where('current_step_order', 1)),

            'in_progress' => Tab::make('In Progress')
                ->badge(fn (): int => $this->countByStep($base(), 2, true))
                ->query(fn (Builder $query): Builder => $query->where('current_step_order', '>=', 2)),

            'all' => Tab::make('All'),
        ];
    }

    /**
     * @param  array<int, int>  $statuses
     */
    protected function countByStep(Builder $query, int $step, bool $atLeast = false): int
    {
        return $atLeast
            ? $query->where('current_step_order', '>=', $step)->count()
            : $query->where('current_step_order', $step)->count();
    }
}
