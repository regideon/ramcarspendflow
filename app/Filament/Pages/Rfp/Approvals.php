<?php

namespace App\Filament\Pages\Rfp;

use App\Enums\RfpRequestType;
use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Filament\Concerns\HasCommentsChat;
use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Models\Company;
use App\Models\Rfp\DueType;
use App\Models\Rfp\Item;
use App\Models\Vendor;
use App\Services\Rfp\QueueChip;
use App\Services\Rfp\RfpQueueChips;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Redesigned "RFP Approvals" (Google Stitch): the approval queue for Admin and Accounting with
 * urgency chips, one tab per step of the route, a filter panel with removable filter chips and
 * a column picker. Rows open the redesigned approval screen (ReviewRfpApproval).
 * The old list (RfpApprovalResource) is hidden from the menu: see docs/13-hidden-pages.md.
 */
class Approvals extends Page
{
    use HasCommentsChat;
    use WithPagination;

    protected string $view = 'filament.pages.rfp.approvals';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'RFP';

    protected static ?string $navigationLabel = 'RFP Approvals';

    protected static ?string $title = 'RFP Approvals';

    protected static ?string $slug = 'rfp/approvals';

    protected static ?int $navigationSort = 2;

    public const array SORTABLE = ['rfp_number', 'due_date', 'total_php_amount', 'status', 'created_at', 'updated_at'];

    /** Columns the Columns menu can hide (RFP ID always shows). */
    public const array COLUMNS = [
        'due' => 'Due',
        'urgency' => 'Urgency',
        'company' => 'Company',
        'payee' => 'Payee',
        'vendors' => 'Vendors',
        'lines' => 'Lines',
        'amount' => 'Total Amount',
        'requestor' => 'Requestor',
        'status' => 'Status',
        'approver' => 'Current Approver',
        'created' => 'Created Date',
        'updated' => 'Last Update',
    ];

    /** Filters that narrow the list, in the order their chips show. */
    public const array FILTERS = ['company', 'vendor', 'dueType', 'type', 'statuses', 'from', 'until'];

    #[Url]
    public string $tab = 'mine';

    /** An urgency chip narrowing the list (see RfpQueueChips::forApprovals()). */
    #[Url]
    public ?string $focus = null;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public ?string $company = null;

    #[Url]
    public ?string $vendor = null;

    #[Url]
    public ?string $dueType = null;

    #[Url]
    public ?string $type = null;

    /** @var list<string> */
    #[Url]
    public array $statuses = [];

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $until = null;

    #[Url]
    public string $sort = 'created_at';

    #[Url]
    public string $direction = 'desc';

    public int $perPage = 10;

    public static function canAccess(): bool
    {
        return RfpApprovalResource::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        return RfpApprovalResource::getNavigationBadge();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return RfpApprovalResource::getNavigationBadgeColor();
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return RfpApprovalResource::getNavigationBadgeTooltip();
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [static::getUrl() => 'RFP approvals', 'List'];
    }

    public function updated(string $property): void
    {
        if (in_array(str($property)->before('.')->toString(), ['search', 'perPage', ...self::FILTERS], true)) {
            $this->resetPage();
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, $this->tabs()) ? $tab : 'all';

        // A tab picked by hand clears a chip that belongs to another tab.
        if (($chip = $this->queueChip($this->focus)) && $chip->tab !== $this->tab) {
            $this->focus = null;
        }

        $this->resetPage();
    }

    /**
     * Pick an urgency chip: opens its tab and narrows the list; picking it again clears it.
     */
    public function pickFocus(string $key): void
    {
        $chip = $this->queueChip($key);

        if (! $chip) {
            return;
        }

        $this->tab = $chip->tab;
        $this->focus = ($this->focus === $key || ! $chip->filters()) ? null : $key;
        $this->resetPage();
    }

    public function removeFilter(string $filter): void
    {
        if (in_array($filter, self::FILTERS, true)) {
            $this->reset($filter);
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'focus', ...self::FILTERS);
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

    public function export(): StreamedResponse
    {
        $rfps = $this->filteredQuery()->with(['company', 'dueType', 'vendor', 'requestor', 'currentApprover'])->withCount('items')->orderByDesc('created_at')->get();

        return response()->streamDownload(function () use ($rfps): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['RFP ID', 'Due', 'Urgency', 'Company', 'Payee', 'Lines', 'Total Amount (PHP)', 'Requestor', 'Status', 'Current Approver', 'Created Date', 'Last Update']);

            foreach ($rfps as $rfp) {
                fputcsv($out, [
                    $rfp->rfp_number,
                    $rfp->due_date?->toDateString(),
                    $rfp->dueType?->name,
                    $rfp->company?->short_name,
                    $rfp->vendor?->name,
                    $rfp->items_count,
                    $rfp->total_php_amount,
                    $rfp->requestor?->name,
                    $rfp->status->getLabel(),
                    $rfp->currentApproverLabel(),
                    $rfp->created_at?->toDateTimeString(),
                    $rfp->updated_at?->toDateTimeString(),
                ]);
            }

            fclose($out);
        }, 'rfp-approvals.csv', ['Content-Type' => 'text/csv']);
    }

    #[Computed]
    public function rfps(): LengthAwarePaginator
    {
        $sort = in_array($this->sort, self::SORTABLE, true) ? $this->sort : 'created_at';

        return $this->filteredQuery()
            ->with(['company', 'dueType', 'vendor', 'requestor', 'currentApprover', 'items.currency', 'items.vendor', 'items.oneTimeVendor'])
            ->withCount('items')
            ->withUnreadCommentsCountFor(auth()->user())
            // RFP numbers sort in creation order
            ->orderBy($sort === 'rfp_number' ? 'id' : $sort, $this->direction === 'asc' ? 'asc' : 'desc')
            ->orderByDesc('id')
            ->paginate($this->perPage);
    }

    /**
     * Tabs follow the RFP route: "For my action" first, then one tab per step (Accounting
     * only, otherwise a single "In progress"), then the outcomes and "All".
     *
     * @return array<string, array{label: string, icon: Heroicon, group: string, step?: int}>
     */
    public function tabs(): array
    {
        $tabs = ['mine' => ['label' => 'For my action', 'icon' => Heroicon::OutlinedBellAlert, 'group' => 'mine']];

        if (auth()->user()->hasAnyRole([RoleName::Admin, ...RoleName::rfpAccounting()])) {
            $tabs += [
                'for_ap_review' => ['label' => 'AP Processor', 'icon' => Heroicon::OutlinedDocumentMagnifyingGlass, 'group' => 'route', 'step' => 1],
                'for_approval' => ['label' => 'Matrix Approval', 'icon' => Heroicon::OutlinedUserGroup, 'group' => 'route', 'step' => 2],
                'for_ap_processing' => ['label' => 'AP Processor', 'icon' => Heroicon::OutlinedCalculator, 'group' => 'route', 'step' => 3],
                'with_ap_supervisor' => ['label' => 'AP Supervisor', 'icon' => Heroicon::OutlinedShieldCheck, 'group' => 'route', 'step' => 4],
                'with_ap_manager' => ['label' => 'AP Manager', 'icon' => Heroicon::OutlinedShieldCheck, 'group' => 'route', 'step' => 5],
                'for_treasury' => ['label' => 'Treasury', 'icon' => Heroicon::OutlinedBanknotes, 'group' => 'route', 'step' => 6],
            ];
        } else {
            $tabs['in_progress'] = ['label' => 'In progress', 'icon' => Heroicon::OutlinedArrowPath, 'group' => 'route'];
        }

        return $tabs + [
            'completed' => ['label' => 'Payment Released', 'icon' => Heroicon::OutlinedCheckBadge, 'group' => 'outcome'],
            'for_revision' => ['label' => 'Returned to Requestor', 'icon' => Heroicon::OutlinedArrowUturnLeft, 'group' => 'outcome'],
            'rejected' => ['label' => 'Rejected', 'icon' => Heroicon::OutlinedXCircle, 'group' => 'outcome'],
            'cancelled' => ['label' => 'Cancelled', 'icon' => Heroicon::OutlinedNoSymbol, 'group' => 'outcome'],
            'all' => ['label' => 'All', 'icon' => Heroicon::OutlinedQueueList, 'group' => 'outcome'],
        ];
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function tabCounts(): array
    {
        return collect($this->tabs())
            ->except('all')
            ->map(fn (array $tab, string $key): int => $this->applyTab($this->baseQuery(), $key)->count())
            ->all();
    }

    /**
     * @return list<array{chip: QueueChip, count: int, active: bool}>
     */
    #[Computed]
    public function chips(): array
    {
        return array_map(fn (QueueChip $chip): array => [
            'chip' => $chip,
            'count' => $chip->apply($this->applyTab($this->baseQuery(), $chip->tab))->count(),
            'active' => $this->focus === $chip->key,
        ], RfpQueueChips::forApprovals(auth()->user()));
    }

    /**
     * Options for the filter panel, limited to what appears in the user's approvals.
     *
     * @return array{companies: array<int|string, string>, vendors: array<int|string, string>, dueTypes: array<int|string, string>, types: array<string, string>, statuses: array<string, string>}
     */
    #[Computed]
    public function filterOptions(): array
    {
        $rfpIds = $this->baseQuery()->select('rfps.id');

        return [
            'companies' => Company::query()
                ->whereIn('id', $this->baseQuery()->select('company_id'))
                ->orderBy('short_name')
                ->get()
                ->mapWithKeys(fn (Company $company): array => [$company->id => $company->short_name ?: $company->name])
                ->all(),
            'vendors' => Vendor::query()
                ->where(fn (Builder $query) => $query
                    ->whereIn('id', $this->baseQuery()->select('vendor_id'))
                    ->orWhereIn('id', Item::query()->whereIn('rfp_id', $rfpIds)->whereNotNull('vendor_id')->select('vendor_id')))
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Vendor $vendor): array => [$vendor->id => "{$vendor->code} · {$vendor->name}"])
                ->all(),
            'dueTypes' => DueType::query()->orderBy('name')->pluck('name', 'id')->all(),
            'types' => collect(RfpRequestType::cases())->mapWithKeys(fn (RfpRequestType $type): array => [$type->value => $type->getLabel()])->all(),
            'statuses' => collect(RfpStatus::cases())->mapWithKeys(fn (RfpStatus $status): array => [$status->value => $status->getLabel()])->all(),
        ];
    }

    /**
     * The filters in use, as removable chips.
     *
     * @return array<string, array{label: string, value: string}>
     */
    #[Computed]
    public function activeFilters(): array
    {
        $options = $this->filterOptions;
        $date = fn (?string $value): string => Carbon::parse($value)->format('M j, Y');

        return array_filter([
            'company' => filled($this->company) ? ['label' => 'Company', 'value' => $options['companies'][$this->company] ?? $this->company] : null,
            'vendor' => filled($this->vendor) ? ['label' => 'Vendor', 'value' => $options['vendors'][$this->vendor] ?? $this->vendor] : null,
            'dueType' => filled($this->dueType) ? ['label' => 'Urgency', 'value' => $options['dueTypes'][$this->dueType] ?? $this->dueType] : null,
            'type' => filled($this->type) ? ['label' => 'RFP type', 'value' => $options['types'][$this->type] ?? $this->type] : null,
            'statuses' => $this->statuses !== [] ? ['label' => 'Status', 'value' => collect($this->statuses)->map(fn (string $status): string => $options['statuses'][$status] ?? $status)->implode(', ')] : null,
            'from' => filled($this->from) ? ['label' => 'Start date', 'value' => $date($this->from)] : null,
            'until' => filled($this->until) ? ['label' => 'End date', 'value' => $date($this->until)] : null,
        ]);
    }

    /**
     * User Guide stops (see resources/views/components/user-guide.blade.php).
     *
     * @return list<array{target: string, title: string, body: string}>
     */
    public function guideSteps(): array
    {
        return [
            ['target' => 'chips', 'title' => 'What to work on first', 'body' => 'Overdue, due today and urgent RFPs, plus the chips for your role. Click a chip to show only those RFPs; click it again to clear.'],
            ['target' => 'tabs', 'title' => 'Follow the route', 'body' => 'For my action lists every RFP waiting for you. The numbered tabs follow the route, AP Processor to Treasury, and the last tabs hold the outcomes. Each tab shows its count.'],
            ['target' => 'toolbar', 'title' => 'Search and filters', 'body' => 'Search by RFP ID, invoice no., payee, vendor or requestor. Filters narrows by company, vendor, urgency, RFP type, status and dates; each filter in use shows as a chip you can remove. Columns hides the ones you do not need.'],
            ['target' => 'table', 'title' => 'The queue', 'body' => 'Red due dates are overdue, amber ones are due today. Current Approver shows who holds the RFP now. Click a row to open it and take your action.'],
            ['target' => 'messages', 'title' => 'Messages', 'body' => 'The chat bubble holds every conversation on these RFPs. A red dot on a row means it has unread messages.'],
        ];
    }

    protected function queueChip(?string $key): ?QueueChip
    {
        return $key ? Arr::first(RfpQueueChips::forApprovals(auth()->user()), fn (QueueChip $chip): bool => $chip->key === $key) : null;
    }

    protected function baseQuery(): Builder
    {
        return RfpApprovalResource::getEloquentQuery();
    }

    protected function applyTab(Builder $query, string $tab): Builder
    {
        $status = fn (RfpStatus $status, ?RoleName $role = null): Builder => $query
            ->where('status', $status)
            ->when($role, fn (Builder $query) => $query->where('current_approver_role', $role));

        return match ($tab) {
            'mine' => $query->awaiting(auth()->user()),
            'for_ap_review' => $status(RfpStatus::ForApReview),
            'for_approval' => $status(RfpStatus::ForApproval),
            'for_ap_processing' => $status(RfpStatus::ForApProcessing),
            'with_ap_supervisor' => $status(RfpStatus::ForAccountingApproval, RoleName::ApSupervisor),
            'with_ap_manager' => $status(RfpStatus::ForAccountingApproval, RoleName::ApManager),
            'for_treasury' => $status(RfpStatus::ForTreasury),
            'in_progress' => $query->whereNotIn('status', [RfpStatus::ForRevision, ...RfpStatus::final()]),
            'completed' => $status(RfpStatus::Completed),
            'for_revision' => $status(RfpStatus::ForRevision),
            'rejected' => $status(RfpStatus::Rejected),
            'cancelled' => $status(RfpStatus::Cancelled),
            default => $query,
        };
    }

    protected function filteredQuery(): Builder
    {
        $query = $this->applyTab($this->baseQuery(), $this->tab);
        $query = $this->queueChip($this->focus)?->apply($query) ?? $query;
        $search = trim($this->search);

        return $query
            ->when(filled($search), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('rfp_number', 'like', "%{$search}%")
                ->orWhereHas('vendor', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                ->orWhereHas('requestor', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                ->orWhereHas('items', fn (Builder $items) => $items
                    ->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('vendor', fn (Builder $vendor) => $vendor->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                    ->orWhereHas('oneTimeVendor', fn (Builder $vendor) => $vendor->where('name', 'like', "%{$search}%")->orWhere('tin', 'like', "%{$search}%")))))
            ->when(filled($this->company), fn (Builder $query) => $query->where('company_id', $this->company))
            ->when(filled($this->vendor), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('vendor_id', $this->vendor)
                ->orWhereHas('items', fn (Builder $items) => $items->where('vendor_id', $this->vendor))))
            ->when(filled($this->dueType), fn (Builder $query) => $query->where('rfp_due_type_id', $this->dueType))
            ->when(filled($this->type), fn (Builder $query) => $query->where('request_type', $this->type))
            ->when($this->statuses !== [], fn (Builder $query) => $query->whereIn('status', $this->statuses))
            ->when(filled($this->from), fn (Builder $query) => $query->whereDate('created_at', '>=', $this->from))
            ->when(filled($this->until), fn (Builder $query) => $query->whereDate('created_at', '<=', $this->until));
    }
}
