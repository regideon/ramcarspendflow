<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\RoleName;
use App\Models\Company;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
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
                            ->label('General roles')
                            ->helperText('Roles that apply in every company. Approval roles are set per company below.')
                            ->relationship('roles', 'name', fn (Builder $query) => $query->whereNotIn('name', static::companyScopedRoleNames()))
                            ->multiple()
                            ->preload()
                            ->live()
                            ->columnSpanFull(),
                        Select::make('company_id')
                            ->label('Home company')
                            ->relationship('company', 'name'),
                        Select::make('companies')
                            ->label('Companies')
                            ->helperText('Companies the user may request for.')
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
                Section::make('Approval roles by company')
                    ->description('Who approves where. The same person can hold a role in several companies, and different roles in different companies.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('company_roles')
                            ->hiddenLabel()
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add approval role')
                            ->schema([
                                Select::make('role')
                                    ->options(collect(RoleName::companyScoped())->mapWithKeys(fn (RoleName $role): array => [$role->value => $role->value])->all())
                                    ->required()
                                    ->distinct(),
                                Select::make('companies')
                                    ->options(fn (): array => Company::query()->orderBy('code')->get()
                                        ->mapWithKeys(fn (Company $company): array => [$company->id => "{$company->code} - {$company->name}"])
                                        ->all())
                                    ->multiple()
                                    ->required(),
                            ]),
                    ]),
            ]);
    }

    /**
     * @return list<string>
     */
    protected static function companyScopedRoleNames(): array
    {
        return array_map(fn (RoleName $role): string => $role->value, RoleName::companyScoped());
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
