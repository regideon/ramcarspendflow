<?php

namespace App\Filament\Clusters\MasterData\Resources\AccountGroups;

use App\Filament\Clusters\MasterData\MasterDataCluster;
use App\Filament\Clusters\MasterData\Resources\AccountGroups\Pages\ManageAccountGroups;
use App\Filament\Concerns\RestrictedToAdmins;
use App\Models\AccountGroup;
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

class AccountGroupResource extends Resource
{
    use RestrictedToAdmins;

    protected static ?string $model = AccountGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $cluster = MasterDataCluster::class;

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Vendor Account Groups';

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Account group')
                    ->required()
                    ->maxLength(4)
                    ->unique(ignoreRecord: true)
                    ->helperText('SAP vendor account group (KTOKK)'),
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
                    ->label('Code')
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
            'index' => ManageAccountGroups::route('/'),
        ];
    }
}
