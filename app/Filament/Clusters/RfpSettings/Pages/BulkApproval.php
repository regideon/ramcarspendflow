<?php

namespace App\Filament\Clusters\RfpSettings\Pages;

use App\Filament\Clusters\RfpSettings\RfpSettingsCluster;
use App\Filament\Concerns\RestrictedToAdmins;
use App\Models\Rfp\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Limits for mass approval in the approver portal (/my/approvals).
 *
 * @property-read Schema $form
 */
class BulkApproval extends Page
{
    use RestrictedToAdmins;

    protected static ?string $cluster = RfpSettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static ?string $navigationLabel = 'Bulk Approval';

    protected static ?string $title = 'Bulk Approval';

    protected static ?int $navigationSort = 4;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(Setting::current()->only(['bulk_approval_item_limit', 'bulk_approval_total_limit']));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Mass approval limits')
                        ->description('Approvers can tick several RFPs on their Approvals page and approve them in one go. These limits keep high amounts under one-by-one review. Changes apply right away.')
                        ->columns(2)
                        ->schema([
                            TextInput::make('bulk_approval_item_limit')
                                ->label('Highest amount per RFP')
                                ->helperText('RFPs of this amount and above can’t be ticked: they must be approved one by one.')
                                ->prefix('PHP')
                                ->numeric()
                                ->minValue(1)
                                ->step(0.01)
                                ->required(),
                            TextInput::make('bulk_approval_total_limit')
                                ->label('Highest total per batch')
                                ->helperText('The ticked RFPs together may add up to this amount at most.')
                                ->prefix('PHP')
                                ->numeric()
                                ->minValue(fn (Get $get): float => (float) $get('bulk_approval_item_limit') ?: 1)
                                ->validationMessages(['min' => 'Must be at least the highest amount per RFP.'])
                                ->step(0.01)
                                ->required(),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save limits')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        Setting::current()->update($this->form->getState());

        Notification::make()
            ->title('Bulk approval limits saved')
            ->success()
            ->send();
    }
}
