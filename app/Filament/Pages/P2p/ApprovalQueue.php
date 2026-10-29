<?php

namespace App\Filament\Pages\P2p;

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Filament\Concerns\HasCommentsChat;
use App\Filament\Resources\P2p\Approvals\ApprovalResource;
use App\Filament\Resources\P2p\Approvals\Pages\ListApprovals;
use App\Models\P2p\Invoice;
use App\Services\P2p\P2pThreeWayMatchService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Redesigned P2P Approvals (Google Stitch): the AP worklist with the same tabs, columns,
 * filters and Review action as the old list, which stays under P2P (OLD) › P2P Approvals
 * ({@see ApprovalResource}) for comparison. Rows open the redesigned review ({@see ReviewAccountsPayable}).
 */
class ApprovalQueue extends Page
{
    use HasCommentsChat;
    use WithPagination;

    protected string $view = 'filament.pages.p2p.approval-queue';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'P2P';

    protected static ?string $navigationLabel = 'P2P Approvals';

    protected static ?string $title = 'P2P Approvals';

    protected static ?string $slug = 'p2p/approval-queue';

    protected static ?int $navigationSort = 2;

    /** Same tabs as the old list. Only "For my action" is actionable; the rest are overviews. */
    public const array TABS = [
        'mine' => ['label' => 'For my action', 'icon' => 'heroicon-m-user'],
        'all' => ['label' => 'All', 'icon' => 'heroicon-m-banknotes'],
        'escalated' => ['label' => 'Escalated', 'icon' => 'heroicon-m-exclamation-triangle'],
        'ap_processor' => ['label' => 'AP Processor', 'icon' => 'heroicon-m-clipboard-document-list'],
        'ap_checker' => ['label' => 'AP Checker', 'icon' => 'heroicon-m-clipboard-document-check'],
        'posted' => ['label' => 'Posted to SAP', 'icon' => 'heroicon-m-check-circle'],
    ];

    public const array SORTABLE = ['submitted_at', 'invoice_at', 'amount_submitted', 'match_flag_count'];

    /** Waiting this many days or more is flagged (amber), twice as long is red. */
    public const int WAITING_DAYS = 3;

    #[Url]
    public string $tab = 'mine';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public ?string $status = null;

    #[Url(as: 'review')]
    public bool $needsReview = false;

    #[Url]
    public string $sort = 'submitted_at';

    #[Url]
    public string $direction = 'asc';

    public int $perPage = 10;

    public static function canAccess(): bool
    {
        return ApprovalResource::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        return ApprovalResource::getNavigationBadge();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return ApprovalResource::getNavigationBadgeColor();
    }

    public function getSubheading(): Htmlable
    {
        $mine = $this->baseQuery()->actionableBy(auth()->user());

        return new HtmlString(view('filament.pages.p2p.approval-queue.subheading', [
            'role' => $this->roleLabel(),
            'mine' => (clone $mine)->count(),
            'waiting' => $mine->where('submitted_at', '<', now()->subDays(self::WAITING_DAYS))->count(),
        ])->render());
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'needsReview', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : 'mine';
        $this->resetPage();
    }

    public function toggleNeedsReview(): void
    {
        $this->needsReview = ! $this->needsReview;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'needsReview');
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        $this->direction = $this->sort === $column && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
        $this->resetPage();
    }

    /** "Sorted by: Oldest first" ⇄ "Newest first" on the waiting time. */
    public function toggleOldestFirst(): void
    {
        $this->direction = $this->sort === 'submitted_at' && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = 'submitted_at';
        $this->resetPage();
    }

    public function export(): StreamedResponse
    {
        $invoices = $this->filteredQuery()->with(['vendor', 'company', 'linkedRfps'])->orderBy('submitted_at')->get();

        return response()->streamDownload(function () use ($invoices): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['GRC no.', 'Vendor code', 'Vendor', 'Company', 'PO', 'Reference', 'Invoice date', 'GR', 'Amount', 'RFP down payment', 'Invoice check', 'Status', 'Submitted']);

            foreach ($invoices as $invoice) {
                fputcsv($out, [
                    $invoice->grc_number,
                    $invoice->vendor?->code,
                    $invoice->vendor?->name,
                    $invoice->company?->short_name,
                    implode(', ', (array) $invoice->po_numbers),
                    implode(', ', (array) $invoice->reference_number),
                    $invoice->invoice_at?->toDateString(),
                    implode(', ', (array) $invoice->gr_numbers),
                    $invoice->amount_submitted,
                    $invoice->linkedRfps->pluck('rfp_number')->implode(', '),
                    $invoice->match_flag_count > 0 ? "{$invoice->match_flag_count} to check" : ($invoice->invoice_match !== null ? 'Checked' : ''),
                    $invoice->status?->getLabel(),
                    $invoice->submitted_at?->toDateTimeString(),
                ]);
            }

            fclose($out);
        }, 'p2p-approvals-'.$this->tab.'.csv', ['Content-Type' => 'text/csv']);
    }

    #[Computed]
    public function invoices(): LengthAwarePaginator
    {
        $sort = in_array($this->sort, self::SORTABLE, true) ? $this->sort : 'submitted_at';

        return $this->filteredQuery()
            ->with(['vendor', 'company', 'linkedRfps'])
            ->withUnreadCommentsCountFor(auth()->user())
            ->orderBy($sort, $this->direction === 'desc' ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate($this->perPage);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function tabCounts(): array
    {
        return collect(self::TABS)
            ->map(fn (array $tab, string $key): int => $this->applyTab($this->baseQuery(), $key)->count())
            ->all();
    }

    /**
     * The statuses in the viewer's own queue (as on the old list), so the filter never offers one
     * they cannot receive.
     *
     * @return array<int|string, string>
     */
    #[Computed]
    public function statusOptions(): array
    {
        $statuses = auth()->user()?->hasRole(RoleName::ApSupervisor) ?? false
            ? [P2pStatus::SubmittedToChecker, P2pStatus::CheckerReview]
            : [P2pStatus::SubmittedToAp, P2pStatus::ApReview, P2pStatus::ReturnedToApProcessor];

        return collect($statuses)->mapWithKeys(fn (P2pStatus $status): array => [$status->value => $status->getLabel()])->all();
    }

    /**
     * The 3-way breakdown for a row (label, color, reason), memoised per request.
     *
     * @return array{label: string, color: string, reason: string}
     */
    public function threeWay(Invoice $invoice): array
    {
        /** @var array<int, array{label: string, color: string, reason: string}> $cache */
        static $cache = [];

        return $cache[$invoice->getKey()] ??= app(P2pThreeWayMatchService::class)->breakdownForInvoice($invoice);
    }

    /** The redesigned review; the old list keeps opening BulkAccountsPayable. */
    public function reviewUrl(Invoice $invoice): string
    {
        return ReviewAccountsPayable::getUrl(['invoice' => $invoice->id]);
    }

    /**
     * User Guide stops (see resources/views/components/user-guide.blade.php).
     *
     * @return list<array{target: string, title: string, body: string}>
     */
    public function guideSteps(): array
    {
        return [
            ['target' => 'tabs', 'title' => 'Your queue and the others', 'body' => 'For my action is your own worklist: the only tab you can act on. All, Escalated, AP Processor, AP Checker and Posted to SAP are read-only overviews. The number on each tab is how many submissions it holds.'],
            ['target' => 'filters', 'title' => 'Find a submission', 'body' => 'Search by GRC no., invoice no. or vendor, narrow by status, or show only submissions whose invoice needs review. Oldest first by default, as the queue is worked in order; click to switch.'],
            ['target' => 'table', 'title' => 'The submissions', 'body' => '3-Way Match compares the PO, goods receipt and invoice. Invoice Check shows lines that don\'t match the vendor\'s invoice. Waiting turns amber after 3 days and red after 6. Click Review (or the row) to process a submission.'],
            ['target' => 'export', 'title' => 'Export', 'body' => 'Download what the current tab and filters show as a CSV file.'],
            ['target' => 'messages', 'title' => 'Messages', 'body' => 'The bubble on a row opens its conversation; the number counts unread messages.'],
        ];
    }

    protected function roleLabel(): string
    {
        $user = auth()->user();

        return match (true) {
            $user?->hasRole(RoleName::ApSupervisor) => 'AP Checker',
            $user?->hasRole(RoleName::ApProcessor) => 'AP Processor',
            default => 'Admin',
        };
    }

    /**
     * Every submission the viewer may see (as on the old list).
     */
    protected function baseQuery(): Builder
    {
        return Invoice::query()->visibleTo(auth()->user());
    }

    /** Same queries as the old list's tabs ({@see ListApprovals}). */
    protected function applyTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'mine' => $query->actionableBy(auth()->user()),
            'escalated' => $query->whereIn('status', [
                P2pStatus::EscalatedByVendor, P2pStatus::EscalatedByProcessor, P2pStatus::EscalateToProcurement,
                P2pStatus::EscalatedToWarehouse, P2pStatus::PartiallyEscalated,
            ]),
            'ap_processor' => $query->actionableByApProcessor(),
            'ap_checker' => $query->actionableByApSupervisor(),
            'posted' => $query->whereIn('status', [P2pStatus::InvoiceParked, P2pStatus::InvoicePosted, P2pStatus::Completed]),
            default => $query,
        };
    }

    protected function filteredQuery(): Builder
    {
        return $this->applyTab($this->baseQuery(), $this->tab)
            ->when(filled($this->search), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('grc_number', 'like', "%{$this->search}%")
                ->orWhere('reference_number', 'like', "%{$this->search}%")
                ->orWhere('invoice_number', 'like', "%{$this->search}%")
                ->orWhere('po_numbers', 'like', "%{$this->search}%")
                ->orWhere('gr_numbers', 'like', "%{$this->search}%")
                ->orWhereHas('vendor', fn (Builder $query) => $query
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%"))))
            ->when(filled($this->status), fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->needsReview, fn (Builder $query) => $query->where('match_flag_count', '>', 0));
    }
}
