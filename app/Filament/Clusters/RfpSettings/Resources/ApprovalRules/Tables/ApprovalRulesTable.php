<?php

namespace App\Filament\Clusters\RfpSettings\Resources\ApprovalRules\Tables;

use App\Enums\RfpRequestType;
use App\Models\Rfp\ApprovalRule;
use App\Models\Rfp\ApprovalRuleStep;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ApprovalRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('steps.role'))
            ->columns([
                TextColumn::make('request_type')
                    ->badge()
                    ->placeholder('Any'),
                IconColumn::make('is_direct_to_vp')
                    ->label('Direct to VP')
                    ->boolean(),
                TextColumn::make('max_amount')
                    ->label('Up to')
                    ->money('PHP')
                    ->placeholder('No limit'),
                TextColumn::make('approvers')
                    ->state(fn (ApprovalRule $record): string => $record->steps
                        ->groupBy('step_order')
                        ->map(fn ($steps): string => $steps->map(fn (ApprovalRuleStep $step): string => $step->role->name)->implode(' + '))
                        ->implode(' → ')),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderBy('is_direct_to_vp')
                ->orderBy('request_type')
                ->orderByRaw('max_amount is null')
                ->orderBy('max_amount'))
            ->paginated(false)
            ->filters([
                SelectFilter::make('request_type')
                    ->options(RfpRequestType::class),
                TernaryFilter::make('is_direct_to_vp')
                    ->label('Direct to VP'),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
