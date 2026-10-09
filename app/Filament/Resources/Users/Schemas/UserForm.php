<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\RoleName;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->minLength(8)
                            ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Leave blank to keep the current password.' : null),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Inactive users cannot sign in.')
                            ->default(true)
                            ->inline(false),
                    ]),
                Section::make('Access')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->live()
                            ->columnSpanFull(),
                        Select::make('company_id')
                            ->label('Home company')
                            ->relationship('company', 'name'),
                        Select::make('companies')
                            ->label('Companies')
                            ->helperText('Companies the user may request or approve for.')
                            ->relationship('companies', 'name')
                            ->multiple()
                            ->preload(),
                        Select::make('vendor_id')
                            ->label('Vendor')
                            ->helperText('The SAP vendor this login belongs to.')
                            ->relationship('vendor', 'name')
                            ->searchable(['code', 'name'])
                            ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->code} - {$record->name}")
                            ->visible(fn (Get $get): bool => static::hasVendorRole($get('roles')))
                            ->required(fn (Get $get): bool => static::hasVendorRole($get('roles'))),
                    ]),
            ]);
    }

    /**
     * @param  array<int, int|string>|Collection<int, int|string>|null  $roleIds
     */
    protected static function hasVendorRole(array|Collection|null $roleIds): bool
    {
        if (blank($roleIds)) {
            return false;
        }

        return Role::query()
            ->whereKey($roleIds)
            ->where('name', RoleName::Vendor->value)
            ->exists();
    }
}
