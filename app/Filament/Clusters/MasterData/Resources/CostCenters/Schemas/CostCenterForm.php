<?php

namespace App\Filament\Clusters\MasterData\Resources\CostCenters\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class CostCenterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cost center')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('company_id')
                            ->label('Company')
                            ->relationship('company', 'name')
                            ->required()
                            ->live(),
                        TextInput::make('code')
                            ->label('Cost center')
                            ->helperText('SAP cost center (KOSTL)')
                            ->required()
                            ->maxLength(10)
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('company_id', $get('company_id'))),
                        TextInput::make('description')
                            ->required()
                            ->maxLength(150),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }
}
