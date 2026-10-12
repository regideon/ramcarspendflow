<?php

namespace App\Filament\Clusters\P2pSettings\Resources\EscalationReasons;

use App\Enums\P2pStatus;
use App\Filament\Clusters\P2pSettings\P2pSettingsCluster;
use App\Filament\Clusters\P2pSettings\Resources\EscalationReasons\Pages\ManageEscalationReasons;
use App\Filament\Concerns\RestrictedToAdmins;
use App\Models\P2p\EscalationReason;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EscalationReasonResource extends Resource
{
    use RestrictedToAdmins;

    protected static ?string $model = EscalationReason::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static ?string $cluster = P2pSettingsCluster::class;

    protected static ?string $navigationLabel = 'Escalation Reasons';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->maxLength(50)->unique(ignoreRecord: true),
            TextInput::make('name')->required()->maxLength(100),
            Select::make('origin_role')
                ->label('Raised by')
                ->options(['vendor' => 'Vendor', 'processor' => 'AP Processor', 'any' => 'Any'])
                ->required(),
            TextInput::make('sort')->numeric()->default(0),
            Toggle::make('is_active')->label('Active')->default(true),
            Repeater::make('steps')
                ->relationship()
                ->label('Route')
                ->schema([
                    TextInput::make('step_order')->numeric()->required()->default(1),
                    Select::make('role')
                        ->options(['procurement' => 'Procurement', 'warehouse' => 'Warehouse', 'vendor' => 'Vendor', 'processor' => 'AP Processor', 'origin' => 'Originator'])
                        ->required(),
                    Select::make('status_id')
                        ->label('Status at hop')
                        ->options(P2pStatus::class)
                        ->searchable(),
                    TextInput::make('action_label')->maxLength(50),
                    TextInput::make('action')
                        ->maxLength(50)
                        ->helperText('Custom hop action key, e.g. reverse_and_regr'),
                    Toggle::make('override_header_status')
                        ->label('Terminal (returns to originator)'),
                ])
                ->columns(2)
                ->orderColumn('step_order')
                ->defaultItems(1)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('code')->searchable()->toggleable(),
                TextColumn::make('origin_role')->label('Raised by')->badge()->color('gray'),
                TextColumn::make('steps_count')->label('Hops')->counts('steps')->badge(),
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
            'index' => ManageEscalationReasons::route('/'),
        ];
    }
}
