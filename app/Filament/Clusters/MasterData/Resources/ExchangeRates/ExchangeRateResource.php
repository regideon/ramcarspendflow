<?php

namespace App\Filament\Clusters\MasterData\Resources\ExchangeRates;

use App\Filament\Clusters\MasterData\MasterDataCluster;
use App\Filament\Clusters\MasterData\Resources\ExchangeRates\Pages\ManageExchangeRates;
use App\Filament\Concerns\RestrictedToAdmins;
use App\Models\Currency;
use App\Models\ExchangeRate;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class ExchangeRateResource extends Resource
{
    use RestrictedToAdmins;

    protected static ?string $model = ExchangeRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $cluster = MasterDataCluster::class;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('from_currency_id')
                    ->label('From')
                    ->relationship('fromCurrency', 'code')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('to_currency_id')
                    ->label('To')
                    ->relationship('toCurrency', 'code')
                    ->searchable()
                    ->preload()
                    ->default(fn (): ?int => Currency::query()->where('code', 'PHP')->value('id'))
                    ->different('from_currency_id')
                    ->required(),
                TextInput::make('rate')
                    ->required()
                    ->numeric()
                    ->gt(0)
                    ->helperText('Units of the "To" currency for 1 unit of the "From" currency.'),
                DatePicker::make('valid_from')
                    ->required()
                    ->default(now())
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                        ->where('from_currency_id', $get('from_currency_id'))
                        ->where('to_currency_id', $get('to_currency_id'))),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('valid_from')
                    ->date()
                    ->sortable(),
                TextColumn::make('fromCurrency.code')
                    ->label('From')
                    ->searchable(),
                TextColumn::make('toCurrency.code')
                    ->label('To')
                    ->searchable(),
                TextColumn::make('rate')
                    ->numeric(decimalPlaces: 6),
            ])
            ->defaultSort('valid_from', 'desc')
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExchangeRates::route('/'),
        ];
    }
}
