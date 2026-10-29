<?php

namespace App\Filament\Resources\P2p\Escalations\Pages;

use App\Filament\Concerns\HasCommentsChat;
use App\Filament\Resources\P2p\Escalations\EscalationResource;
use App\Models\P2p\Escalation;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListEscalations extends ListRecords
{
    use HasCommentsChat;

    protected static string $resource = EscalationResource::class;

    public function getTabs(): array
    {
        $roles = Escalation::hopRolesForUser(auth()->user());
        $base = fn (): Builder => EscalationResource::getEloquentQuery();

        return [
            'for_my_action' => Tab::make('For My Action')
                ->badge(fn (): int => $base()
                    ->where('status', Escalation::STATUS_OPEN)
                    ->whereIn('current_role', $roles)
                    ->count())
                ->query(fn (Builder $query): Builder => $query
                    ->where('status', Escalation::STATUS_OPEN)
                    ->whereIn('current_role', $roles)),

            'in_progress' => Tab::make('In Progress')
                ->badge(fn (): int => $base()
                    ->where('status', Escalation::STATUS_OPEN)
                    ->whereNotIn('current_role', $roles)
                    ->count())
                ->query(fn (Builder $query): Builder => $query
                    ->where('status', Escalation::STATUS_OPEN)
                    ->whereNotIn('current_role', $roles)),

            'all' => Tab::make('All')
                ->badge(fn (): int => $base()->count())
                ->query(fn (Builder $query): Builder => $query),
        ];
    }
}
