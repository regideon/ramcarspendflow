<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Filament\Concerns\HasCommentsChat;
use App\Filament\Resources\P2p\Approvals\ApprovalResource;
use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Services\Accounting\ApProcessorDashboard;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * The redesigned dashboard (Google Stitch, docs/stitch/), built role by role next to the
 * old Dashboard, which stays as "Dashboard (OLD)" for comparison. AP Processor first.
 */
class Home extends Page
{
    use HasCommentsChat;

    protected string $view = 'filament.pages.home.ap-processor';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $slug = 'home';

    protected static ?int $navigationSort = -3;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(RoleName::ApProcessor) ?? false;
    }

    public function getTitle(): string
    {
        return 'Dashboard';
    }

    public function getHeading(): string
    {
        $greeting = match (true) {
            now()->hour < 12 => 'Good morning',
            now()->hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        return $greeting.', '.str(auth()->user()->name)->before(' ');
    }

    public function getSubheading(): Htmlable
    {
        ['total' => $total, 'overdue' => $overdue] = $this->dashboard()->queueSize();
        $role = $this->role()->value;

        return new HtmlString(view('filament.pages.home.partials.subheading', compact('role', 'total', 'overdue'))->render());
    }

    /**
     * The role this dashboard serves. A user can hold many roles; the badge under the
     * greeting names the one whose work this page shows.
     */
    public function role(): RoleName
    {
        return RoleName::ApProcessor;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openRfpQueue')
                ->label('Open RFP queue')
                ->icon(Heroicon::OutlinedQueueList)
                ->outlined()
                ->extraAttributes(['class' => 'sf-btn-soft'])
                ->url(fn (): string => RfpApprovalResource::getUrl('index')),

            Action::make('processP2pInvoices')
                ->label('Process P2P invoices')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->url(fn (): string => ApprovalResource::getUrl('index')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $dashboard = $this->dashboard();

        return [
            'backOnDesk' => $dashboard->backOnDesk(),
            'stats' => $dashboard->stats(),
            'processedPerDay' => $dashboard->processedPerDay(),
            'queue' => $dashboard->queue(),
            'queueTotal' => $dashboard->queueSize()['total'],
            'aging' => $dashboard->aging(),
            'throughput' => $dashboard->throughput(),
            'escalations' => $dashboard->escalations(),
            'escalationSummary' => $dashboard->escalationSummary(),
            'tax' => $dashboard->taxSummary(),
            'decisions' => $dashboard->decisions(),
            'returnRate' => $dashboard->supervisorReturnRate(),
            'returnReasons' => $dashboard->returnReasons(),
            'guide' => $this->guideSteps(),
        ];
    }

    /**
     * User Guide walkthrough, one stop per section. `target` is the data-tour key
     * the view puts on that section; stops whose section is not on the page are skipped.
     *
     * @return list<array{target: string, title: string, body: string}>
     */
    public function guideSteps(): array
    {
        return [
            ['target' => 'back-on-desk', 'title' => 'Back on your desk', 'body' => 'Items handed back to you: work the AP Supervisor returned with remarks, and P2P escalations that were resolved. Read the remark, fix the item, and send it on again.'],
            ['target' => 'kpis', 'title' => 'Your numbers at a glance', 'body' => 'How many items wait in each step and their total amount. RFP first review is Step 2, RFP AP processing is Step 4, P2P to process are vendor invoices sent to AP. Overdue counts everything past its due date.'],
            ['target' => 'work-queue', 'title' => 'Work queue', 'body' => 'Everything waiting for you, most urgent first. Use the tabs to show only RFP, P2P, returned or overdue items, or type in the filter box. The age chip turns amber after 3 days and red after 6. Click Process to open the item.'],
            ['target' => 'queue-aging', 'title' => 'Queue aging', 'body' => 'How long items have been sitting with you, grouped by age. Keep the red bars short: anything older than 5 days slows the payment.'],
            ['target' => 'keeping-up', 'title' => 'Keeping up', 'body' => 'Items that arrived in your queue (dashed grey) against items you finished (indigo), per day for the last 14 days. When indigo stays above grey, your backlog is shrinking.'],
            ['target' => 'escalations', 'title' => 'Escalations raised', 'body' => 'P2P invoices you escalated that are still open. The route shows who has the escalation now. It comes back to your queue once it is resolved.'],
            ['target' => 'tax-summary', 'title' => 'Tax summary', 'body' => 'VAT and withholding tax (EWT) on the invoices you processed. A warning appears when an invoice is still missing its withholding tax code.'],
            ['target' => 'insights', 'title' => 'Insights', 'body' => 'How often the AP Supervisor sends your work back and why, and what you did with the items you finished. Use it to avoid the most common returns.'],
            ['target' => 'messages', 'title' => 'Messages', 'body' => 'Comments on RFPs and invoices. Mention a colleague with @ to ask a question without leaving the page. The red badge counts unread messages.'],
        ];
    }

    protected function dashboard(): ApProcessorDashboard
    {
        return new ApProcessorDashboard(auth()->user());
    }
}
