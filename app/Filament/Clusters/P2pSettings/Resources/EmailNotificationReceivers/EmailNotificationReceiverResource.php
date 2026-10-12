<?php

namespace App\Filament\Clusters\P2pSettings\Resources\EmailNotificationReceivers;

use App\Filament\Clusters\P2pSettings\P2pSettingsCluster;
use App\Filament\Clusters\P2pSettings\Resources\EmailNotificationReceivers\Pages\ManageEmailNotificationReceivers;
use App\Filament\Concerns\RestrictedToAdmins;
use App\Models\P2p\EmailNotificationReceiver;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmailNotificationReceiverResource extends Resource
{
    use RestrictedToAdmins;

    protected static ?string $model = EmailNotificationReceiver::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $cluster = P2pSettingsCluster::class;

    protected static ?string $navigationLabel = 'Points of Contact';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'email';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('email')->email()->required()->maxLength(150),
            TextInput::make('name')->maxLength(150),
            TextInput::make('notification_type')->maxLength(50)->default('overdue_warning'),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('notification_type')->label('Type')->badge()->color('gray'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('email')
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmailNotificationReceivers::route('/'),
        ];
    }
}
