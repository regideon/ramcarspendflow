<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Filament\Resources\Roles\Schemas\RoleForm;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_system')
                    ->label('System')
                    ->state(fn (Role $record): bool => RoleForm::isSystemRole($record))
                    ->boolean(),
                TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (Role $record): bool => RoleForm::isSystemRole($record)),
            ]);
    }
}
