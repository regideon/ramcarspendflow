<?php

namespace App\Filament\Clusters\MasterData\Resources\PaymentMethods;

use App\Filament\Clusters\MasterData\MasterDataCluster;
use App\Filament\Clusters\MasterData\Resources\PaymentMethods\Pages\ManagePaymentMethods;
use App\Filament\Concerns\RestrictedToAdmins;
use App\Models\PaymentMethod;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class PaymentMethodResource extends Resource
{
    use RestrictedToAdmins;

    protected static ?string $model = PaymentMethod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $cluster = MasterDataCluster::class;

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('country')
                    ->required()
                    ->maxLength(3)
                    ->default('PH'),
                TextInput::make('code')
                    ->label('Payment method')
                    ->required()
                    ->length(1)
                    ->helperText('SAP payment method (ZLSCH)')
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('country', $get('country'))),
                TextInput::make('description')
                    ->required()
                    ->maxLength(150),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('country')
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Method')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('code')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePaymentMethods::route('/'),
        ];
    }
}
