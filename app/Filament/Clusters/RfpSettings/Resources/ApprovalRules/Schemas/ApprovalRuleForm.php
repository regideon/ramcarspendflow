<?php

namespace App\Filament\Clusters\RfpSettings\Resources\ApprovalRules\Schemas;

use App\Enums\RfpRequestType;
use App\Enums\RoleName;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class ApprovalRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tier')
                    ->description('A tier covers amounts above the next lower tier up to and including its limit. Amounts are in PHP.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('request_type')
                            ->options(RfpRequestType::class)
                            ->placeholder('Any request type')
                            ->helperText('Leave blank to apply to every request type (used by the Direct to VP tiers).'),
                        Toggle::make('is_direct_to_vp')
                            ->label('Direct to VP')
                            ->inline(false),
                        TextInput::make('max_amount')
                            ->label('Up to (PHP)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('PHP')
                            ->helperText('Inclusive. Leave blank for no upper limit.'),
                        TextInput::make('description')
                            ->maxLength(150),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),
                Section::make('Approvers')
                    ->description('Approved in step order. Roles with the same step number approve in parallel.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('steps')
                            ->hiddenLabel()
                            ->relationship()
                            ->columns(2)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel('Add approver')
                            ->schema([
                                TextInput::make('step_order')
                                    ->label('Step')
                                    ->required()
                                    ->integer()
                                    ->minValue(1)
                                    ->default(1),
                                Select::make('role_id')
                                    ->label('Role')
                                    ->options(fn (): array => Role::query()
                                        ->whereIn('name', array_map(fn (RoleName $roleName): string => $roleName->value, RoleName::rfpApprovers()))
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->required()
                                    ->distinct(),
                            ]),
                    ]),
            ]);
    }
}
