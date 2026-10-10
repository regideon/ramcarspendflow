<?php

namespace App\Filament\Clusters\P2pSettings\Resources\MaterialClassifications;

use App\Enums\RoleName;
use App\Filament\Clusters\P2pSettings\P2pSettingsCluster;
use App\Filament\Clusters\P2pSettings\Resources\MaterialClassifications\Pages\ManageMaterialClassifications;
use App\Filament\Concerns\RestrictedToAdmins;
use App\Models\P2p\MaterialClassification;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MaterialClassificationResource extends Resource
{
    use RestrictedToAdmins;

    protected static ?string $model = MaterialClassification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $cluster = P2pSettingsCluster::class;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->required()
                ->maxLength(50)
                ->unique(ignoreRecord: true),
            TextInput::make('name')
                ->required()
                ->maxLength(100),
            Select::make('role_name')
                ->label('Owning role')
                ->options(collect(RoleName::cases())->mapWithKeys(fn (RoleName $role): array => [$role->value => $role->value]))
                ->searchable()
                ->helperText('Warehouse escalation hop routed to this classification.'),
            Select::make('color')
                ->options(['gray' => 'Gray', 'info' => 'Blue', 'warning' => 'Amber', 'danger' => 'Red', 'success' => 'Green'])
                ->default('gray')
                ->required(),
            TextInput::make('sort')
                ->numeric()
                ->default(0),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('role_name')->label('Role')->badge()->color('gray')->placeholder('—'),
                TextColumn::make('slocClassifications_count')
                    ->label('SLOCs')
                    ->counts('slocClassifications')
                    ->badge(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('sort')
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMaterialClassifications::route('/'),
        ];
    }
}
