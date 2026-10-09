<?php

namespace App\Filament\Clusters\MasterData\Resources\Vendors\RelationManagers;

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
 * Company codes the vendor is extended to in SAP, with the per-company fields.
 */
class CompaniesRelationManager extends RelationManager
{
    protected static string $relationship = 'companies';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(static::pivotFields());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('code')
                    ->label('Company code'),
                TextColumn::make('name'),
                TextColumn::make('reconciliation_account')
                    ->label('Recon. account'),
                TextColumn::make('payment_terms'),
                TextColumn::make('payment_methods'),
                IconColumn::make('pivot.is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
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
            TextInput::make('reconciliation_account')
                ->label('Reconciliation account')
                ->helperText('SAP AKONT')
                ->required()
                ->maxLength(10),
            TextInput::make('payment_terms')
                ->helperText('SAP ZTERM, e.g. V30N')
                ->maxLength(4),
            TextInput::make('payment_methods')
                ->helperText('SAP ZWELS: allowed method codes, e.g. CT')
                ->maxLength(10),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ];
    }
}
