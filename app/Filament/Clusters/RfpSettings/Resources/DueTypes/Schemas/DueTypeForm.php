<?php

namespace App\Filament\Clusters\RfpSettings\Resources\DueTypes\Schemas;

use App\Models\Rfp\DueType;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class DueTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Due type')
                    ->description('Sets the due date of an RFP, counted from the day it is submitted. Changes apply to new submissions only.')
                    ->columns(['default' => 1, 'md' => 3])
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(50),
                        TextInput::make('code')
                            ->helperText('Used by the system. Cannot be changed later.')
                            ->required()
                            ->alphaDash()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->disabledOn('edit'),
                        TextInput::make('description')
                            ->placeholder('e.g. 1-day processing')
                            ->maxLength(150),
                        TextInput::make('processing_days')
                            ->label('Processing days')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(365)
                            ->required()
                            ->live(onBlur: true)
                            ->belowContent(fn (Get $get): ?string => is_numeric($get('processing_days'))
                                ? 'Submitted today → due '.(new DueType([
                                    'processing_days' => (int) $get('processing_days'),
                                    'business_days_only' => (bool) $get('business_days_only'),
                                ]))->dueDateFrom()->format('D, M j')
                                : null),
                        Toggle::make('business_days_only')
                            ->label('Business days only')
                            ->helperText('Skip Saturdays and Sundays.')
                            ->default(true)
                            ->inline(false)
                            ->live(),
                        TextInput::make('sort_order')
                            ->integer()
                            ->default(0),
                        Toggle::make('is_default')
                            ->label('Default')
                            ->helperText('Pre-selected on new RFPs.')
                            ->inline(false),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }
}
