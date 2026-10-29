<?php

namespace App\Filament\Pages\Rfp;

use App\Enums\RfpRequestType;
use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Concerns\HasCommentsChat;
use App\Filament\Resources\Rfps\RfpResource;
use App\Filament\Resources\Rfps\Widgets\RfpProgress;
use App\Models\Company;
use App\Models\Rfp\DueType;
use App\Models\Rfp\Rfp;
use App\Services\Rfp\QueueChip;
use App\Services\Rfp\RfpQueueChips;
use App\Support\Peso;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Redesigned "My requests" (Google Stitch, docs/stitch/hub-rfps/): the requestor's own RFPs
 * with summary chips, saved-view tabs, filters, bulk actions and a preview drawer.
 * The old list (RfpResource) is hidden from the menu: see docs/13-hidden-pages.md.
 */
class MyRequests extends Page
{
    use HasCommentsChat;
    use WithPagination;

    protected string $view = 'filament.pages.rfp.my-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'RFP';

    protected static ?string $navigationLabel = 'RFP Requests';

    protected static ?string $title = 'My requests';

    protected static ?string $slug = 'rfp/my-requests';

    protected static ?int $navigationSort = 1;

    public const array TABS = [
        'all' => 'All requests',
        'drafts' => 'Drafts',
        'for_revision' => 'For revision',
        'in_progress' => 'In progress',
        'closed' => 'Closed',
    ];

    public const array SORTABLE = ['rfp_number', 'total_php_amount', 'due_date', 'updated_at'];

    #[Url]
    public string $tab = 'all';

    /** A summary chip or suggestion narrowing the list (see focusOptions()). */
    #[Url]
    public ?string $focus = null;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public ?string $status = null;

    #[Url]
    public ?string $type = null;

    #[Url]
    public ?string $dueType = null;

    #[Url]
    public ?string $company = null;

    #[Url]
    public string $sort = 'updated_at';

    #[Url]
    public string $direction = 'desc';

    public int $perPage = 10;

    /** @var list<string> */
    public array $selected = [];

    #[Url(as: 'preview')]
    public ?int $previewId = null;

    public static function canAccess(): bool
    {
        return RfpResource::canAccess();
    }

    public function getSubheading(): Htmlable
    {
        $base = RfpResource::getEloquentQuery();
        $total = (clone $base)->count();
        $inProgress = (float) $this->applyTab(clone $base, 'in_progress')->sum('total_php_amount');

        return new HtmlString(view('filament.pages.rfp.partials.subheading', [
            'role' => RoleName::Requestor->value,
            'total' => $total,
            'inProgress' => Peso::format($inProgress, compact: true),
        ])->render());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importReceipts')
                ->label('Import receipts with AI')
                ->icon(Heroicon::Sparkles)
                ->outlined()
                ->extraAttributes(['class' => 'sf-btn-soft'])
                ->badge('Beta')
                ->url(fn (): string => RfpResource::getUrl('create', ['type' => RfpRequestType::Standard->value]))
                ->visible(fn (): bool => RfpResource::canCreate()),

            ActionGroup::make(collect(RfpRequestType::cases())->map(
                fn (RfpRequestType $type): Action => Action::make('new'.$type->name)
                    ->label($type->getLabel())
                    ->url(RfpResource::getUrl('create', ['type' => $type->value])),
            )->all())
                ->label('New request')
                ->icon(Heroicon::Plus)
                ->button()
                ->visible(fn (): bool => RfpResource::canCreate()),
        ];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'type', 'dueType', 'company', 'perPage'], true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : 'all';

        // A tab picked by hand clears a chip that belongs to another tab.
        if (($option = $this->focusOption($this->focus)) && $option->tab !== $this->tab) {
            $this->focus = null;
        }

        $this->resetPage();
        $this->selected = [];
    }

    /**
     * Pick a summary chip or a suggestion: opens its tab and narrows the list; picking it again clears it.
     */
    public function pickFocus(string $key): void
    {
        $option = $this->focusOption($key);

        if (! $option) {
            return;
        }

        $this->tab = $option->tab;
        $this->focus = ($this->focus === $key || ! $option->filters()) ? null : $key;
        $this->resetPage();
        $this->selected = [];
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'type', 'dueType', 'company', 'focus');
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        $this->direction = $this->sort === $column && $this->direction === 'desc' ? 'asc' : 'desc';
        $this->sort = $column;
        $this->resetPage();
    }

    /**
     * Header checkbox: select every row on the page, or clear them when all are selected.
     *
     * @param  list<int|string>  $ids
     */
    public function togglePage(array $ids): void
    {
        $ids = array_map('strval', $ids);

        $this->selected = array_diff($ids, $this->selected) === []
            ? array_values(array_diff($this->selected, $ids))
            : array_values(array_unique([...$this->selected, ...$ids]));
    }

    public function openPreview(int $id): void
    {
        $this->previewId = $id;
        unset($this->preview);
    }

    public function closePreview(): void
    {
        $this->previewId = null;
    }

    public function export(): StreamedResponse
    {
        return $this->csv($this->filteredQuery(), 'my-requests.csv');
    }

    public function exportSelected(): StreamedResponse
    {
        return $this->csv(RfpResource::getEloquentQuery()->whereKey($this->selected), 'my-requests-selected.csv');
    }

    public function deleteSelectedDrafts(): void
    {
        $drafts = RfpResource::getEloquentQuery()
            ->whereKey($this->selected)
            ->where('status', RfpStatus::Draft)
            ->get()
            ->filter(fn (Rfp $rfp): bool => RfpResource::canDelete($rfp));

        $drafts->each->delete();
        $this->selected = [];

        Notification::make()
            ->title($drafts->isEmpty() ? 'No drafts selected' : $drafts->count().' '.str('draft')->plural($drafts->count()).' deleted')
            ->body($drafts->isEmpty() ? 'Only drafts can be deleted. Submitted requests stay on record.' : null)
            ->{$drafts->isEmpty() ? 'warning' : 'success'}()
            ->send();
    }

    #[Computed]
    public function rfps(): LengthAwarePaginator
    {
        $sort = in_array($this->sort, self::SORTABLE, true) ? $this->sort : 'updated_at';

        return $this->filteredQuery()
            ->with(['company', 'dueType', 'vendor', 'updater', 'items.currency'])
            ->withUnreadCommentsCountFor(auth()->user())
            ->orderBy($sort, $this->direction === 'asc' ? 'asc' : 'desc')
            ->orderByDesc('id')
            ->paginate($this->perPage);
    }

    #[Computed]
    public function preview(): ?Rfp
    {
        return $this->previewId
            ? RfpResource::getEloquentQuery()
                ->with(['company', 'dueType', 'vendor', 'requestor', 'currentApprover', 'items.currency', 'approvalSteps.actor', 'histories.user'])
                ->find($this->previewId)
            : null;
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function tabCounts(): array
    {
        return collect(self::TABS)
            ->map(fn (string $label, string $tab): int => $this->applyTab(RfpResource::getEloquentQuery(), $tab)->count())
            ->all();
    }

    /**
     * The five summary chips with their counts and amounts.
     *
     * @return list<array{chip: QueueChip, count: int, amount: float, active: bool}>
     */
    #[Computed]
    public function chips(): array
    {
        return array_map(function (QueueChip $chip): array {
            $query = $chip->apply($this->applyTab(RfpResource::getEloquentQuery(), $chip->tab));

            return [
                'chip' => $chip,
                'count' => (clone $query)->count(),
                'amount' => (float) $query->sum('total_php_amount'),
                'active' => $this->focus === $chip->key || (! $chip->filters() && $this->focus === null && $this->tab === $chip->tab && $this->tab !== 'all'),
            ];
        }, $this->summaryChips());
    }

    /**
     * Options for the filter pills.
     *
     * @return array{statuses: array<string, string>, types: array<string, string>, dueTypes: Collection<int, string>, companies: Collection<int, string>}
     */
    #[Computed]
    public function filterOptions(): array
    {
        return [
            'statuses' => collect(RfpStatus::cases())->mapWithKeys(fn (RfpStatus $status): array => [$status->value => $status->getLabel()])->all(),
            'types' => collect(RfpRequestType::cases())->mapWithKeys(fn (RfpRequestType $type): array => [$type->value => $type->getLabel()])->all(),
            'dueTypes' => DueType::query()->orderBy('name')->pluck('name', 'id'),
            'companies' => Company::query()
                ->whereIn('id', RfpResource::getEloquentQuery()->select('company_id'))
                ->orderBy('short_name')
                ->pluck('short_name', 'id'),
        ];
    }

    /**
     * Where the RFP is on its route, AP review to Treasury: done, current, revision, failed, skipped or upcoming.
     *
     * @return list<array{label: string, state: string}>
     */
    public function track(Rfp $rfp): array
    {
        $widget = new RfpProgress;
        $widget->record = $rfp;

        return array_map(
            fn (array $step): array => ['label' => $step['label'], 'state' => $step['state']],
            array_slice($widget->getSteps(), 1, 5),
        );
    }

    /**
     * User Guide stops (see resources/views/components/user-guide.blade.php).
     *
     * @return list<array{target: string, title: string, body: string}>
     */
    public function guideSteps(): array
    {
        return [
            ['target' => 'search', 'title' => 'Search and suggestions', 'body' => 'Type an RFP no., an invoice no. or a payee to find a request. The suggestions below the box are ready-made filters for common questions.'],
            ['target' => 'chips', 'title' => 'Your requests at a glance', 'body' => 'Counts and amounts for what needs you (Returned, Drafts) and what is on its way (In progress, Overdue, Paid this month). Click a card to show only those requests; click it again to clear.'],
            ['target' => 'tabs', 'title' => 'Views and filters', 'body' => 'Switch between all, drafts, for revision, in progress and closed requests. Narrow further with Status, Type, Due type and Company, or export what you see to CSV.'],
            ['target' => 'table', 'title' => 'Your requests', 'body' => 'One row per request. The workflow track shows its five steps: AP review, Approval, AP processing, Accounting approval and Treasury. Red due dates are overdue. Click a row to preview it without leaving the list.'],
            ['target' => 'messages', 'title' => 'Messages', 'body' => 'The chat bubble holds every conversation on your requests. The number on a row counts its unread messages.'],
        ];
    }

    /**
     * @return list<QueueChip>
     */
    protected function summaryChips(): array
    {
        $chips = collect(RfpQueueChips::forRequests())->keyBy('key');

        return [
            $chips['returned'],
            $chips['drafts'],
            $chips['in_progress'],
            RfpQueueChips::overdue('in_progress'),
            $chips['paid_this_month'],
        ];
    }

    /**
     * Suggestions under the search box: ready-made filters.
     *
     * @return list<QueueChip>
     */
    public function suggestions(): array
    {
        return [
            new QueueChip('stuck', "What's stuck in approval?", Heroicon::OutlinedPauseCircle, 'info', 'in_progress',
                fn (Builder $query): Builder => $query->where('updated_at', '<', now()->subDays(3))),
            new QueueChip('returned_this_week', 'Returned to me this week', Heroicon::OutlinedArrowUturnLeft, 'warning', 'for_revision',
                fn (Builder $query): Builder => $query->where('updated_at', '>=', now()->startOfWeek())),
            new QueueChip('unpaid_reimbursements', 'Reimbursements not yet paid', Heroicon::OutlinedReceiptPercent, 'gray', 'all',
                fn (Builder $query): Builder => $query->where('request_type', RfpRequestType::Reimbursement)->whereNotIn('status', RfpStatus::final())),
        ];
    }

    protected function focusOption(?string $key): ?QueueChip
    {
        return $key ? Arr::first([...$this->summaryChips(), ...$this->suggestions()], fn (QueueChip $chip): bool => $chip->key === $key) : null;
    }

    protected function applyTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'drafts' => $query->where('status', RfpStatus::Draft),
            'for_revision' => $query->where('status', RfpStatus::ForRevision),
            'in_progress' => $query->whereIn('status', array_filter(RfpStatus::cases(), fn (RfpStatus $status): bool => $status->isAwaitingApproval())),
            'closed' => $query->whereIn('status', RfpStatus::final()),
            default => $query,
        };
    }

    protected function filteredQuery(): Builder
    {
        $query = $this->applyTab(RfpResource::getEloquentQuery(), $this->tab);
        $query = $this->focusOption($this->focus)?->apply($query) ?? $query;

        return $query
            ->when(filled($this->search), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('rfp_number', 'like', "%{$this->search}%")
                ->orWhereHas('items', fn (Builder $query) => $query->where('invoice_number', 'like', "%{$this->search}%"))
                ->orWhereHas('vendor', fn (Builder $query) => $query->where('name', 'like', "%{$this->search}%"))))
            ->when(filled($this->status), fn (Builder $query) => $query->where('status', $this->status))
            ->when(filled($this->type), fn (Builder $query) => $query->where('request_type', $this->type))
            ->when(filled($this->dueType), fn (Builder $query) => $query->where('rfp_due_type_id', $this->dueType))
            ->when(filled($this->company), fn (Builder $query) => $query->where('company_id', $this->company));
    }

    protected function csv(Builder $query, string $filename): StreamedResponse
    {
        $rfps = $query->with(['company', 'dueType', 'vendor'])->orderByDesc('updated_at')->get();

        return response()->streamDownload(function () use ($rfps): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['RFP no.', 'Status', 'Company', 'Type', 'Due type', 'Payee', 'Amount (PHP)', 'Due date', 'Created', 'Last update']);

            foreach ($rfps as $rfp) {
                fputcsv($out, [
                    $rfp->rfp_number,
                    $rfp->status->getLabel(),
                    $rfp->company?->short_name,
                    $rfp->request_type?->getLabel(),
                    $rfp->dueType?->name,
                    $rfp->vendor?->name,
                    $rfp->total_php_amount,
                    $rfp->due_date?->toDateString(),
                    $rfp->created_at?->toDateTimeString(),
                    $rfp->updated_at?->toDateTimeString(),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
