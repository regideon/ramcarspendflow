<?php

namespace App\Filament\Clusters\MasterData\Resources\Vendors\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VendorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Vendor')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('code')
                            ->label('Vendor number')
                            ->helperText('SAP vendor number (LIFNR)')
                            ->required()
                            ->maxLength(10)
                            ->unique(ignoreRecord: true),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(150),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255)
                            ->helperText('Receives P2P notifications.'),
                        TextInput::make('tax_number')
                            ->label('TIN')
                            ->maxLength(20),
                        TextInput::make('country')
                            ->required()
                            ->maxLength(3)
                            ->default('PH'),
                        TextInput::make('currency')
                            ->maxLength(5),
                        Select::make('account_group_id')
                            ->label('Account group')
                            ->relationship('accountGroup', 'description')
                            ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->code} - {$record->description}"),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }
}
