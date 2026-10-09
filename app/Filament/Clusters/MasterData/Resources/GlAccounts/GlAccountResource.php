<?php

namespace App\Filament\Clusters\MasterData\Resources\GlAccounts;

use App\Filament\Clusters\MasterData\MasterDataCluster;
use App\Filament\Clusters\MasterData\Resources\GlAccounts\Pages\ManageGlAccounts;
use App\Filament\Concerns\RestrictedToAdmins;
use App\Models\GlAccount;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class GlAccountResource extends Resource
{
    use RestrictedToAdmins;

    protected static ?string $model = GlAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $cluster = MasterDataCluster::class;

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'G/L Accounts';

    protected static ?string $modelLabel = 'G/L account';

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('G/L account')
                    ->required()
                    ->maxLength(10)
                    ->unique(ignoreRecord: true)
                    ->helperText('SAP G/L account (SAKNR)'),
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
                TextColumn::make('code')
                    ->label('G/L account')
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
            'index' => ManageGlAccounts::route('/'),
        ];
    }
}
