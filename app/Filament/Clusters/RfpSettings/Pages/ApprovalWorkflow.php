<?php

namespace App\Filament\Clusters\RfpSettings\Pages;

use App\Enums\RfpRequestType;
use App\Enums\RfpStageKind;
use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Clusters\RfpSettings\RfpSettingsCluster;
use App\Filament\Concerns\RestrictedToAdmins;
use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\Rfp\WorkflowStage;
use App\Services\Rfp\ApprovalEngine;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * The global RFP approval workflow, from AP review to Treasury. RFPs already submitted
 * keep the route they were given. See docs/12-rfp-dynamic-workflow.md.
 *
 * @property-read Schema $form
 */
class ApprovalWorkflow extends Page
{
    use RestrictedToAdmins;

    protected string $view = 'filament.clusters.rfp-settings.pages.approval-workflow';

    protected static ?string $cluster = RfpSettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Approval Workflow';

    protected static ?string $title = 'Approval Workflow';

    protected static ?int $navigationSort = 0;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public ?int $previewCompanyId = null;

    public string $previewRequestType = 'standard';

    public string $previewAmount = '50000';

    public bool $previewDirectToVp = false;

    public function mount(): void
    {
        $this->previewCompanyId = Company::query()->orderBy('code')->value('id');

        $this->form->fill([
            'stages' => WorkflowStage::ordered()
                ->map(fn (WorkflowStage $stage): array => collect($stage->attributesToArray())->except(['id', 'position', 'created_at', 'updated_at'])->all())
                ->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Repeater::make('stages')
                        ->hiddenLabel()
                        ->helperText('Stages run top to bottom after the requestor submits. Changes apply to RFPs submitted from now on.')
                        ->reorderable()
                        ->collapsible()
                        ->columns(4)
                        ->addActionLabel('Add stage')
                        ->itemLabel(fn (array $state): string => trim(($state['label'] ?? '').' · '.(static::kindOf($state['kind'] ?? null)?->getLabel() ?? ''), ' ·'))
                        ->schema([
                            Select::make('kind')
                                ->options(RfpStageKind::class)
                                ->default(RfpStageKind::Approval->value)
                                ->required()
                                ->live(),
                            Select::make('role')
                                ->live()
                                ->options(collect(RoleName::companyScoped())
                                    ->mapWithKeys(fn (RoleName $role): array => [$role->value => $role->value])
                                    ->all())
                                ->visible(fn (Get $get): bool => static::kindOf($get('kind'))?->hasRole() ?? true)
                                ->required(fn (Get $get): bool => static::kindOf($get('kind'))?->hasRole() ?? true),
                            TextInput::make('label')
                                ->live(onBlur: true)
                                ->helperText('Shown in the route and the queue.')
                                ->required()
                                ->maxLength(60),
                            Select::make('status')
                                ->label('RFP status while here')
                                ->options(collect([
                                    RfpStatus::ForApReview, RfpStatus::ForApproval, RfpStatus::ForApProcessing,
                                    RfpStatus::ForAccountingApproval, RfpStatus::ForTreasury,
                                ])->mapWithKeys(fn (RfpStatus $status): array => [$status->value => $status->getLabel()])->all())
                                ->required(),
                            Toggle::make('can_return_to_requestor')
                                ->live()
                                ->label('Can return to requestor'),
                            TextInput::make('return_to_position')
                                ->live(onBlur: true)
                                ->label('Can return to stage no.')
                                ->integer()
                                ->minValue(1)
                                ->helperText('Leave blank if it cannot.'),
                            Toggle::make('can_reject')
                                ->live()
                                ->label('Can reject'),
                            Toggle::make('can_cancel')
                                ->live()
                                ->label('Can cancel'),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save workflow')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * @throws ValidationException
     */
    public function save(): void
    {
        $stages = $this->form->getState()['stages'] ?? [];

        try {
            WorkflowStage::replaceWith(array_values($stages));
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['data.stages' => $exception->errors()['stages']]);
        }

        Notification::make()
            ->title('Approval workflow saved')
            ->success()
            ->send();
    }

    /**
     * The flow diagram for the workflow being edited (saved or not), as it would route a
     * sample request in the preview company, with who holds each role there.
     *
     * @return list<array{position: int, label: string, kind: RfpStageKind, note: ?string, sequences: list<list<array{role: string, people: list<string>}>>, returnsToRequestor: bool, returnsTo: ?string, canReject: bool, canCancel: bool}>
     */
    public function diagram(): array
    {
        $draft = collect($this->data['stages'] ?? [])->values()
            ->map(function (array $row, int $index): ?WorkflowStage {
                $kind = static::kindOf($row['kind'] ?? null);

                if ($kind === null) {
                    return null;
                }

                $role = $row['role'] ?? null;

                return new WorkflowStage([
                    'position' => $index + 1,
                    'kind' => $kind,
                    'role' => $kind->hasRole() && filled($role) ? $role : null,
                    'label' => filled($row['label'] ?? null) ? $row['label'] : $kind->getLabel(),
                    'can_return_to_requestor' => (bool) ($row['can_return_to_requestor'] ?? false),
                    'return_to_position' => filled($row['return_to_position'] ?? null) ? (int) $row['return_to_position'] : null,
                    'can_reject' => (bool) ($row['can_reject'] ?? false),
                    'can_cancel' => (bool) ($row['can_cancel'] ?? false),
                ]);
            })
            ->filter()
            ->values();

        $people = CompanyRole::query()
            ->where('company_id', $this->previewCompanyId)
            ->whereHas('user', fn (Builder $query) => $query->where('is_active', true))
            ->with('user:id,name')
            ->get()
            ->groupBy(fn (CompanyRole $assignment): string => $assignment->role->value)
            ->map(fn ($assignments): array => $assignments->pluck('user.name')->sort()->values()->all());

        $route = app(ApprovalEngine::class)->routeFor(
            $draft,
            RfpRequestType::tryFrom($this->previewRequestType),
            is_numeric($this->previewAmount) ? $this->previewAmount : '0',
            $this->previewDirectToVp,
        );

        return array_map(fn (array $node): array => [
            'position' => $node['stage']->position,
            'label' => $node['stage']->label,
            'kind' => $node['stage']->kind,
            'note' => $node['note'],
            'sequences' => array_map(fn (array $roles): array => array_map(fn (RoleName $role): array => [
                'role' => $role->value,
                'people' => $people->get($role->value, []),
            ], $roles), $node['sequences']),
            'returnsToRequestor' => $node['stage']->can_return_to_requestor,
            'returnsTo' => $node['stage']->return_to_position
                ? $draft->firstWhere('position', $node['stage']->return_to_position)?->label
                : null,
            'canReject' => $node['stage']->can_reject,
            'canCancel' => $node['stage']->can_cancel,
        ], $route);
    }

    /**
     * @return array<int, string>
     */
    public function previewCompanies(): array
    {
        return Company::query()->orderBy('code')->get()
            ->mapWithKeys(fn (Company $company): array => [$company->id => "{$company->code} - {$company->name}"])
            ->all();
    }

    /**
     * A stage kind from form state, which holds the enum or its value.
     */
    protected static function kindOf(mixed $state): ?RfpStageKind
    {
        return $state instanceof RfpStageKind ? $state : RfpStageKind::tryFrom((string) $state);
    }
}
