<?php

namespace App\Filament\Clusters\MasterData\Resources\CostCenters\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * G/L accounts an RFP line may charge to this cost center.
 */
class GlAccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'glAccounts';

    protected static ?string $title = 'G/L accounts';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(static::pivotFields());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('code')
                    ->label('G/L account')
                    ->searchable(),
                TextColumn::make('description')
                    ->searchable(),
                TextColumn::make('expense_group'),
                TextColumn::make('category')
                    ->placeholder('—'),
                TextColumn::make('account_type'),
                IconColumn::make('pivot.is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->recordSelectSearchColumns(['code', 'description'])
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        ...static::pivotFields(),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    protected static function pivotFields(): array
    {
        return [
            TextInput::make('expense_group')
                ->helperText('e.g. Operating Expenses, Manufacturing Expenses')
                ->required()
                ->maxLength(50),
            TextInput::make('category')
                ->maxLength(100),
            TextInput::make('account_type')
                ->required()
                ->maxLength(30)
                ->default('Accounts Payable'),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ];
    }
}
