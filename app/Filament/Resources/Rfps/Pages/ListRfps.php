<?php

namespace App\Filament\Resources\Rfps\Pages;

use App\Enums\RfpStatus;
use App\Filament\Concerns\HasCommentsChat;
use App\Filament\Resources\Rfps\RfpResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRfps extends ListRecords
{
    use HasCommentsChat;

    protected static string $resource = RfpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New request'),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $inProgress = [
            RfpStatus::ForApReview, RfpStatus::ForApproval, RfpStatus::ForApProcessing,
            RfpStatus::ForAccountingApproval, RfpStatus::ForTreasury,
        ];

        return [
            'all' => Tab::make('All'),
            'drafts' => Tab::make('Drafts')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', RfpStatus::Draft))
                ->badge(fn (): int => RfpResource::getEloquentQuery()->where('status', RfpStatus::Draft)->count()),
            'for_revision' => Tab::make('For revision')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', RfpStatus::ForRevision))
                ->badge(fn (): int => RfpResource::getEloquentQuery()->where('status', RfpStatus::ForRevision)->count())
                ->badgeColor('warning'),
            'in_progress' => Tab::make('In progress')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $inProgress)),
            'closed' => Tab::make('Closed')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [RfpStatus::Completed, RfpStatus::Rejected, RfpStatus::Cancelled])),
        ];
    }
}
