<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Enums\RoleName;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Role')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(125)
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (?Role $record): bool => static::isSystemRole($record))
                            ->helperText(fn (?Role $record): ?string => static::isSystemRole($record)
                                ? 'System role: the approval flows refer to it by name, so it cannot be renamed.'
                                : null),
                        TextInput::make('guard_name')
                            ->default('web')
                            ->hidden()
                            ->dehydratedWhenHidden(),
                    ]),
            ]);
    }

    public static function isSystemRole(?Role $role): bool
    {
        return $role !== null && RoleName::tryFrom($role->name) !== null;
    }
}
